<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Onboarding\UserOnboarding;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:onboard-user', 'Create the Keycloak user if missing, give it a workspace from the generic template and send the welcome mail.')]
class OnboardUserCommand extends Command
{
    public function __construct(
        private readonly UserOnboarding $userOnboarding,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address of the user to onboard');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->userOnboarding->onboard($input->getArgument('email'));

        $output->writeln(sprintf('Workspace "%s" (%s) is ready.', $workspace->getName(), $workspace->getId()));

        return Command::SUCCESS;
    }
}
