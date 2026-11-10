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
use App\Service\Onboarding\UserOnboarding;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UserOnboardingTest extends TestCase
{
    private const string TEMPLATE = __DIR__.'/../../fixtures/onboarding/workspace-template.json';
    private const array USER = ['id' => '4fe7bbba-0bb3-496b-8d8c-1234567890aa', 'username' => 'jdoe', 'email' => 'jdoe@example.com'];

    public function testExistingUserGetsAnOwnedWorkspaceAndAWelcomeMail(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('getUsers')
            ->with(['query' => ['email' => 'jdoe@example.com', 'exact' => 'true']])
            ->willReturn([self::USER]);
        $userRepository->expects($this->never())->method('createUser');

        $workspace = $this->onboard($userRepository, $sent);

        $this->assertSame('jdoe', $workspace->getName());
        $this->assertSame('jdoe', $workspace->getSlug());
        $this->assertSame(self::USER['id'], $workspace->getOwnerId());
        $this->assertSame(['en'], $workspace->getEnabledLocales());

        $this->assertInstanceOf(SendEmailNotification::class, $sent);
        $this->assertSame('jdoe@example.com', $sent->email);
        $this->assertSame(UserOnboarding::WELCOME_TOPIC, $sent->topic);
        $this->assertSame('jdoe', $sent->params['username']);
        $this->assertSame($workspace->getId(), $sent->params['workspaceId']);
        $this->assertSame('/workspaces/'.$workspace->getId(), $sent->params['url']);
    }

    public function testMissingUserIsCreatedInKeycloakBeforeOnboarding(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('getUsers')->willReturn([]);
        $userRepository->expects($this->once())->method('createUser')
            ->with($this->callback(fn (array $data): bool => 'jdoe@example.com' === $data['email']
                && 'jdoe@example.com' === $data['username']
                && true === $data['enabled']
                && ['UPDATE_PASSWORD'] === $data['requiredActions']))
            ->willReturn(self::USER);

        $workspace = $this->onboard($userRepository, $sent);

        $this->assertSame(self::USER['id'], $workspace->getOwnerId());
        $this->assertSame('jdoe@example.com', $sent->email);
    }

    private function onboard(UserRepositoryInterface $userRepository, ?SendEmailNotification &$sent): Workspace
    {
        $permissionManager = $this->createMock(PermissionManager::class);
        $permissionManager->expects($this->once())->method('grantUserOnObject')
            ->with(self::USER['id'], $this->isInstanceOf(Workspace::class), PermissionInterface::OWNER);

        $onboarding = new UserOnboarding(
            $userRepository,
            new WorkspaceTemplater($this->entityManagerCreatingWorkspaces(), new NullLogger()),
            $permissionManager,
            $this->notifierManager($sent),
            self::TEMPLATE,
        );

        return $onboarding->onboard('jdoe@example.com');
    }

    private function entityManagerCreatingWorkspaces(): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Workspace::class)->willReturn($repository);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Workspace::class));
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        return $em;
    }

    private function notifierManager(?SendEmailNotification &$sent): NotifierManager
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(
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
