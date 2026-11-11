<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Integration\Ai\Enrichment\Contract\EnrichmentRequest;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IntegrationConfig;
use App\Repository\Core\AssetEmbeddingRepository;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class AiEnrichmentProcessor
{
    private const string PREVIEW_USAGE = 'preview';
    private const array DEFAULT_LOCALES = ['en'];

    public function __construct(
        private AiEnrichmentClient $client,
        private SuggestionMapper $suggestionMapper,
        private ReturnedFileStore $fileStore,
        private BatchAttributeManager $batchAttributeManager,
        private AssetEmbeddingRepository $embeddingRepository,
        private RenditionManager $renditionManager,
        private FileUrlResolver $fileUrlResolver,
        private ApiBudgetLimiter $apiBudgetLimiter,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function enrich(Asset $asset, IntegrationConfig $config): void
    {
        $file = $this->resolveFile($asset, $config);
        $mimeType = $file->getType() ?? 'application/octet-stream';
        $capabilities = $this->client->getCapabilities($config)->supporting($mimeType, $config['capabilities'] ?: null);
        if ([] === $capabilities) {
            $this->logger->info(sprintf('No enrichment capability accepts "%s", asset "%s" skipped', $mimeType, $asset->getId()));

            return;
        }

        $locales = $config['locales'] ?: $asset->getWorkspace()->getEnabledLocales() ?: self::DEFAULT_LOCALES;

        $this->apiBudgetLimiter->acceptIntegrationApiCall($config);
        $reply = $this->client->enrich($config, new EnrichmentRequest(
            $asset->getId(),
            $asset->getWorkspaceId(),
            $mimeType,
            $this->fileUrlResolver->resolveUrl($file),
            $file === $asset->getSource() ? EnrichmentRequest::FILE_ROLE_ORIGINAL : EnrichmentRequest::FILE_ROLE_PREVIEW,
            $capabilities,
            $locales,
        ));

        $input = $this->suggestionMapper->map($asset, $config['attributes'], $reply->suggestions, $locales[0]);
        if ([] !== $input->actions) {
            $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input, null);
        }

        foreach ($reply->files as $returnedFile) {
            $this->fileStore->store($asset, $returnedFile);
        }

        if (null !== $reply->embedding) {
            $this->embeddingRepository->replace($asset, $reply->embedding->model, $reply->embedding->modelVersion, $reply->embedding->vector);
        }

        $this->em->flush();
    }

    private function resolveFile(Asset $asset, IntegrationConfig $config): File
    {
        $renditionName = $config['rendition'];
        $rendition = null !== $renditionName
            ? $this->renditionManager->getAssetRenditionByName($asset->getId(), $renditionName)
                ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" not found for asset "%s". Ensure the rendition creation job was run before!', $renditionName, $asset->getId()))
            : $this->renditionManager->getAssetRenditionUsedAs(self::PREVIEW_USAGE, $asset->getId());

        return $rendition?->getFile() ?? $asset->getSource();
    }
}
