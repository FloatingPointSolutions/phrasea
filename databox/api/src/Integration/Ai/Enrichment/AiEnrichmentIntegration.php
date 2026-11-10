<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\Ai\Enrichment\Exception\EnrichmentFailedException;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\FilterNeedIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Notification\EntityDisableNotifyableException;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Storage\RenditionManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Sends ingested assets to any service implementing the enrichment contract
 * and writes back its attribute suggestions, renditions and embedding.
 */
class AiEnrichmentIntegration extends AbstractIntegration implements WorkflowIntegrationInterface, FilterNeedIntegrationInterface
{
    public function __construct(
        private readonly AiEnrichmentClient $client,
        private readonly RenditionManager $renditionManager,
        private readonly AttributeDefinitionRepository $definitionRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'ai.enrichment';
    }

    public static function getDisplayName(): string
    {
        return 'AI Enrichment';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('endpoint')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Base URL of a service implementing the enrichment contract')
            ->end()
            ->scalarNode('token')
                ->defaultValue('${AI_ENRICHMENT_TOKEN}')
                ->cannotBeEmpty()
                ->info('Bearer token sent to the service')
            ->end()
            ->integerNode('timeout')
                ->defaultValue(120)
                ->min(1)
                ->info('Seconds to wait for the service reply')
            ->end()
            ->scalarNode('rendition')
                ->defaultNull()
                ->info('Rendition sent to the service; defaults to the preview, then to the source file')
            ->end()
            ->arrayNode('capabilities')
                ->info('Capabilities to request; defaults to every one the service advertises for the file type')
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('locales')
                ->info('Locales to request suggestions for; defaults to the workspace enabled locales')
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('attributes')
                ->info('Workspace attribute receiving each service attribute, keyed by the service attribute name')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('attribute')->isRequired()->cannotBeEmpty()->info('Attribute slug')->end()
                        ->floatNode('threshold')->defaultNull()->info('Minimum confidence to be saved into the attribute')->end()
                    ->end()
                ->end()
            ->end()
        ;

        $builder->append($this->createBudgetLimitConfigNode(false, 1000, '1 day'));
    }

    #[\Override]
    public function validateConfiguration(IntegrationConfig $config): void
    {
        if (null !== $config['rendition']) {
            try {
                $this->renditionManager->getRenditionDefinitionByName($config->getWorkspaceId(), $config['rendition']);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('Rendition "%s" not found in the workspace', $config['rendition']), 0, $e);
            }
        }

        foreach ($config['attributes'] as $name => $target) {
            if (null === $this->definitionRepository->getAttributeDefinitionBySlug($config->getWorkspaceId(), $target['attribute'])) {
                throw new \InvalidArgumentException(sprintf('"%s": attribute "%s" not found in the workspace', $name, $target['attribute']));
            }
        }

        if ([] !== $config['capabilities']) {
            try {
                $advertised = $this->client->getCapabilities($config)->getNames();
            } catch (EnrichmentFailedException|TransportExceptionInterface $e) {
                throw new \InvalidArgumentException(sprintf('Enrichment service "%s" is unreachable: %s', $config['endpoint'], $e->getMessage()), 0, $e);
            }

            $unknown = array_diff($config['capabilities'], $advertised);
            if ([] !== $unknown) {
                throw new \InvalidArgumentException(sprintf('Capabilities not advertised by the service: %s', implode(', ', $unknown)));
            }
        }
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        if (!$workflow->getOn()->hasEventName(AssetIngestWorkflowEvent::EVENT)) {
            return [];
        }

        yield WorkflowHelper::createIntegrationJob($config, AiEnrichmentAction::class);
    }

    public function getNeededJobs(IntegrationConfig $config, IntegrationConfig $neededIntegrationConfig, Job $job): ?array
    {
        $rendition = $config['rendition'];
        if (null === $rendition || !$neededIntegrationConfig->getIntegration() instanceof RenditionIntegration) {
            return null;
        }

        try {
            $renditionDefinition = $this->renditionManager
                ->getRenditionDefinitionByName($neededIntegrationConfig->getWorkspaceId(), $rendition);
        } catch (\InvalidArgumentException $e) {
            throw new EntityDisableNotifyableException($config->getWorkspaceIntegration(), sprintf('Rendition "%s" not found', $rendition), sprintf('Rendition "%s" not found in workspace "%s"', $rendition, $neededIntegrationConfig->getWorkspaceIntegration()->getWorkspace()->getName()), $e->getCode(), $e);
        }

        return [
            RenditionIntegration::getJobId($neededIntegrationConfig, $renditionDefinition->getId()),
        ];
    }
}
