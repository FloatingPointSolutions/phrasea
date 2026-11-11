<?php

declare(strict_types=1);

namespace App\Service\Onboarding;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\Workspace;
use App\Repository\Core\WorkspaceRepository;
use App\Service\Workspace\WorkspaceTemplater;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserOnboarding
{
    public const string WELCOME_TOPIC = 'user:welcome';
    private const int SLUG_MAX_LENGTH = 50;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private WorkspaceRepository $workspaceRepository,
        private WorkspaceTemplater $workspaceTemplater,
        private PermissionManager $permissionManager,
        private NotifierManager $notifierManager,
        private ValidatorInterface $validator,
        private string $onboardingWorkspaceTemplate,
    ) {
    }

    public function onboard(string $email): Workspace
    {
        $this->assertValid($email, new Assert\Email());
        $user = $this->findOrCreateUser($email);

        if ($workspace = $this->workspaceRepository->findOneBy(['ownerId' => $user['id']])) {
            return $workspace;
        }

        $workspace = $this->createWorkspace($user);
        $this->permissionManager->grantUserOnObject($user['id'], $workspace, PermissionInterface::OWNER);

        $this->notifierManager->notifyEmail($email, self::WELCOME_TOPIC, [
            'username' => $user['username'],
            'workspaceId' => $workspace->getId(),
            'workspaceName' => $workspace->getName(),
            'url' => '/workspaces/'.$workspace->getId(),
        ]);

        return $workspace;
    }

    private function findOrCreateUser(string $email): array
    {
        $users = $this->userRepository->getUsers(['query' => ['email' => $email, 'exact' => 'true']]);

        return $users[0] ?? $this->createUser($email);
    }

    private function createUser(string $email): array
    {
        $user = $this->userRepository->createUser([
            'username' => $email,
            'email' => $email,
            'enabled' => true,
            'emailVerified' => true,
            'requiredActions' => ['UPDATE_PASSWORD'],
        ]);
        $this->userRepository->sendExecuteActionsEmail($user['id'], ['UPDATE_PASSWORD']);

        return $user;
    }

    private function createWorkspace(array $user): Workspace
    {
        $workspace = new Workspace();
        $workspace->setOwnerId($user['id']);
        $workspace->setName($user['username']);
        $workspace->setSlug($this->slug($user['username']));
        $this->assertValid($workspace);

        $template = $this->workspaceTemplater->decode(new Filesystem()->readFile($this->onboardingWorkspaceTemplate));
        $this->workspaceTemplater->importToWorkspace($workspace, $template);

        return $workspace;
    }

    private function slug(string $username): string
    {
        return new AsciiSlugger()
            ->slug($username)
            ->lower()
            ->truncate(self::SLUG_MAX_LENGTH)
            ->trimEnd('-')
            ->toString();
    }

    private function assertValid(mixed $value, ?Constraint $constraint = null): void
    {
        $violations = $this->validator->validate($value, $constraint);
        if (count($violations) > 0) {
            throw new ValidationFailedException($value, $violations);
        }
    }
}
