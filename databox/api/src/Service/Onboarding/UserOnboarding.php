<?php

declare(strict_types=1);

namespace App\Service\Onboarding;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceTemplater;

/**
 * Turns an email address into a ready-to-use account: a Keycloak user, a workspace
 * imported from the generic template, ownership of that workspace and a welcome mail.
 */
final readonly class UserOnboarding
{
    public const string WELCOME_TOPIC = 'user:welcome';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private WorkspaceTemplater $workspaceTemplater,
        private PermissionManager $permissionManager,
        private NotifierManager $notifierManager,
        private string $onboardingWorkspaceTemplate,
    ) {
    }

    public function onboard(string $email): Workspace
    {
        $user = $this->findOrCreateUser($email);

        $workspace = $this->workspaceTemplater->import($this->readTemplate(), $user['username'], null, $user['id']);
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

        return $users[0] ?? $this->userRepository->createUser([
            'username' => $email,
            'email' => $email,
            'enabled' => true,
            'emailVerified' => true,
            'requiredActions' => ['UPDATE_PASSWORD'],
        ]);
    }

    private function readTemplate(): array
    {
        $json = file_get_contents($this->onboardingWorkspaceTemplate);
        if (false === $json) {
            throw new \RuntimeException(sprintf('Cannot read workspace template "%s"', $this->onboardingWorkspaceTemplate));
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
