<?php

declare(strict_types=1);

namespace App\Tests\Service\Onboarding;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use App\Entity\Core\Workspace;
use App\Repository\Core\WorkspaceRepository;
use App\Service\Onboarding\UserOnboarding;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserOnboardingTest extends TestCase
{
    private const string TEMPLATE = __DIR__.'/../../fixtures/onboarding/workspace-template.json';
    private const string SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/';
    private const string USER_ID = '4fe7bbba-0bb3-496b-8d8c-1234567890aa';
    private const array EXISTING_USER = ['id' => self::USER_ID, 'username' => 'jdoe', 'email' => 'jdoe@example.com'];
    private const string LONG_EMAIL = 'a.very.long.first.name.and.surname@subdomain.example-company.com';

    public function testExistingUserGetsAnOwnedWorkspaceAndAWelcomeMail(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('getUsers')
            ->with(['query' => ['email' => 'jdoe@example.com', 'exact' => 'true']])
            ->willReturn([self::EXISTING_USER]);
        $userRepository->expects($this->never())->method('createUser');
        $userRepository->expects($this->never())->method('sendExecuteActionsEmail');

        $workspace = $this->onboard('jdoe@example.com', $userRepository, $this->workspaceRepository(null), $this->importingEntityManager(), $sent);

        $this->assertSame('jdoe', $workspace->getName());
        $this->assertSame('jdoe', $workspace->getSlug());
        $this->assertSame(self::USER_ID, $workspace->getOwnerId());
        $this->assertSame(['en'], $workspace->getEnabledLocales());

        $this->assertInstanceOf(SendEmailNotification::class, $sent);
        $this->assertSame('jdoe@example.com', $sent->email);
        $this->assertSame(UserOnboarding::WELCOME_TOPIC, $sent->topic);
        $this->assertSame('jdoe', $sent->params['username']);
        $this->assertSame($workspace->getId(), $sent->params['workspaceId']);
        $this->assertSame('/workspaces/'.$workspace->getId(), $sent->params['url']);
    }

    public function testMissingUserIsCreatedInKeycloakAndInvitedToChooseAPassword(): void
    {
        $user = ['id' => self::USER_ID, 'username' => self::LONG_EMAIL, 'email' => self::LONG_EMAIL];

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('getUsers')->willReturn([]);
        $userRepository->expects($this->once())->method('createUser')
            ->with($this->callback(fn (array $data): bool => self::LONG_EMAIL === $data['email']
                && self::LONG_EMAIL === $data['username']
                && true === $data['enabled']
                && ['UPDATE_PASSWORD'] === $data['requiredActions']))
            ->willReturn($user);
        $userRepository->expects($this->once())->method('sendExecuteActionsEmail')->with(self::USER_ID, ['UPDATE_PASSWORD']);

        $workspace = $this->onboard(self::LONG_EMAIL, $userRepository, $this->workspaceRepository(null), $this->importingEntityManager(), $sent);

        $this->assertSame(self::USER_ID, $workspace->getOwnerId());
        $this->assertSame(self::LONG_EMAIL, $workspace->getName());
        $this->assertMatchesRegularExpression(self::SLUG_PATTERN, $workspace->getSlug());
        $this->assertLessThanOrEqual(50, strlen($workspace->getSlug()));
        $this->assertSame(self::LONG_EMAIL, $sent->email);
    }

    public function testAlreadyOnboardedUserKeepsTheirWorkspaceUntouched(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('getUsers')->willReturn([self::EXISTING_USER]);
        $userRepository->expects($this->never())->method('createUser');

        $owned = new Workspace();
        $owned->setOwnerId(self::USER_ID);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $workspace = $this->onboard('jdoe@example.com', $userRepository, $this->workspaceRepository($owned), $em, $sent, expectGrant: false, expectMail: false);

        $this->assertSame($owned, $workspace);
        $this->assertNull($sent);
    }

    public function testInvalidEmailIsRejectedBeforeTouchingKeycloak(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->never())->method('getUsers');
        $userRepository->expects($this->never())->method('createUser');

        $this->expectException(ValidationFailedException::class);

        $this->onboard('not-an-email', $userRepository, $this->workspaceRepository(null), $this->createMock(EntityManagerInterface::class), $sent, expectGrant: false, expectMail: false, validator: $this->rejectingValidator());
    }

    private function onboard(
        string $email,
        UserRepositoryInterface $userRepository,
        WorkspaceRepository $workspaceRepository,
        EntityManagerInterface $em,
        ?SendEmailNotification &$sent,
        bool $expectGrant = true,
        bool $expectMail = true,
        ?ValidatorInterface $validator = null,
    ): Workspace {
        $permissionManager = $this->createMock(PermissionManager::class);
        $permissionManager->expects($expectGrant ? $this->once() : $this->never())->method('grantUserOnObject')
            ->with(self::USER_ID, $this->isInstanceOf(Workspace::class), PermissionInterface::OWNER);

        $onboarding = new UserOnboarding(
            $userRepository,
            $workspaceRepository,
            new WorkspaceTemplater($em, new NullLogger()),
            $permissionManager,
            $this->notifierManager($sent, $expectMail),
            $validator ?? $this->acceptingValidator(),
            self::TEMPLATE,
        );

        return $onboarding->onboard($email);
    }

    private function workspaceRepository(?Workspace $owned): WorkspaceRepository
    {
        $repository = $this->createMock(WorkspaceRepository::class);
        $repository->method('findOneBy')->with(['ownerId' => self::USER_ID])->willReturn($owned);

        return $repository;
    }

    private function importingEntityManager(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Workspace::class));
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        return $em;
    }

    private function acceptingValidator(): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }

    private function rejectingValidator(): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList([
            $this->createMock(ConstraintViolationInterface::class),
        ]));

        return $validator;
    }

    private function notifierManager(?SendEmailNotification &$sent, bool $expectMail): NotifierManager
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($expectMail ? $this->once() : $this->never())->method('dispatch')->willReturnCallback(
            function (SendEmailNotification $message) use (&$sent): Envelope {
                $sent = $message;

                return new Envelope($message);
            }
        );

        return new NotifierManager(
            $bus,
            new TopicRegistry([
                UserOnboarding::WELCOME_TOPIC => ['channels' => ['email'], 'importance' => 'normal', 'user_configurable' => false],
            ]),
            new NotifierState(true),
            $this->createMock(EntityManagerInterface::class),
            new UserDirectoryRegistry([]),
            $this->createMock(Security::class),
        );
    }
}
