<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class AiEnrichmentAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly AiEnrichmentProcessor $processor,
    ) {
    }

    protected function doHandle(RunContext $context): void
    {
        $this->processor->enrich($this->getAsset($context), $this->getIntegrationConfig($context));
    }

    #[\Override]
    protected function shouldRun(Asset $asset): bool
    {
        return null !== $asset->getSource();
    }
}
