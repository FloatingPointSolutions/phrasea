<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Onboarding;

use App\Service\Onboarding\UserOnboarding;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegistrationApprovedHandler
{
    public function __construct(
        private UserOnboarding $userOnboarding,
    ) {
    }

    public function __invoke(RegistrationApproved $message): void
    {
        $this->userOnboarding->onboard($message->getEmail());
    }
}
