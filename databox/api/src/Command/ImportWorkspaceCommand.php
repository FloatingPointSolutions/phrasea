<?php

declare(strict_types=1);

namespace App\Command;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:workspace:import')]
class ImportWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceTemplater $workspaceTemplater,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Import a workspace from a template.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Workspace name to create / import to (defaults to the template Workspace.name)')
            ->addOption('slug', 's', InputOption::VALUE_REQUIRED, 'Workspace slugified name (if creating; defaults to the template Workspace.slug)')
            ->addOption('owner', 'o', InputOption::VALUE_REQUIRED, 'Owner id or username of the imported workspace')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force updating an existing workspace without asking for confirmation')

            ->setHelp(<<<'HELP'
The template data is read from stdin.
  e.g. <info>sf app:workspace:import new_workspace --owner 4fe7bbba-0bb3-496b-8d8c-1234567890aa < template.json</info>
  e.g. <info>sf app:workspace:import --owner admin < template.json</info>
If no <info>name</info> is provided, the template <info>Workspace.name</info> is used.
If the destination workspace does not exist, it is created (<info>--owner</info> is required); If no <info>--slug</info> is provided, the template <info>Workspace.slug</info> or the slugified name is used.
If the destination workspace exists, it is updated with the template data (<info>--force</info> is required; <info>--owner</info> and <info>--slug</info> are ignored).
HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->readTemplate();
        $name = $input->getArgument('name') ?? $data['Workspace']['name'] ?? throw new \InvalidArgumentException('name is required when the template has no Workspace.name');
        $slug = $input->getOption('slug') ?? $data['Workspace']['slug'] ?? null;
        $ownerId = $input->getOption('owner');

        /** @var Workspace $ws */
        $ws = $this->em->getRepository(Workspace::class)->findOneBy(['name' => $name]);
        if ($ws instanceof Workspace) {
            if (!$input->getOption('force')) {
                throw new \InvalidArgumentException('Workspace already exists, use --force to update it.');
            }
            $ownerId = $slug = null;
            $output->writeln('Updating Workspace.');
        } else {
            if (!$ownerId) {
                throw new \InvalidArgumentException('--owner is required for creating a new workspace');
            }
            $ownerId = $this->resolveOwnerId($ownerId);
            $output->writeln('Creating Workspace.');
        }

        $this->workspaceTemplater->import($data, $name, $slug, $ownerId);

        return 0;
    }

    private function readTemplate(): array
    {
        $stat = fstat(STDIN);
        $mode = $stat['mode'] & 0170000; // S_IFMT
        if (0010000 !== $mode && 0100000 !== $mode) {
            throw new \InvalidArgumentException('send template to stdin, e.g. `sf app:workspace:import new_workspace < template.json`');
        }

        return json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    }

    private function resolveOwnerId(string $owner): string
    {
        if (Uuid::isValid($owner)) {
            return $owner;
        }

        $users = $this->userRepository->getUsers(['query' => ['username' => $owner, 'exact' => 'true']]);
        if ([] === $users) {
            throw new \InvalidArgumentException(sprintf('User "%s" not found', $owner));
        }

        return $users[0]['id'];
    }
}
