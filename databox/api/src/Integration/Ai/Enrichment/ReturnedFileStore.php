<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use App\Border\UriDownloader;
use App\Entity\Core\Asset;
use App\Integration\Ai\Enrichment\Contract\ReturnedFile;
use App\Integration\Ai\Enrichment\Exception\EnrichmentFailedException;
use App\Service\Storage\FileManager;
use App\Service\Storage\RenditionManager;
use Psr\Log\LoggerInterface;

/**
 * Stores a file returned by the service as the rendition it is named after.
 *
 * A substitutable definition receives it as a substitution so the rendition
 * build job leaves it untouched; any other definition is rebuilt on the next run.
 */
final readonly class ReturnedFileStore
{
    public function __construct(
        private RenditionManager $renditionManager,
        private FileManager $fileManager,
        private UriDownloader $downloader,
        private LoggerInterface $logger,
    ) {
    }

    public function store(Asset $asset, ReturnedFile $file): void
    {
        if (ReturnedFile::PURPOSE_RENDITION !== $file->purpose || null === $file->name) {
            $this->logger->info(sprintf('Ignoring "%s" file returned for asset "%s": only named renditions are stored', $file->purpose, $asset->getId()));

            return;
        }

        try {
            $definition = $this->renditionManager->getRenditionDefinitionByName($asset->getWorkspaceId(), $file->name);
        } catch (\InvalidArgumentException $e) {
            throw new EnrichmentFailedException(sprintf('Returned file names rendition "%s", unknown in the workspace', $file->name), 0, $e);
        }
        $stored = $this->fileManager->createFileFromPath($asset->getWorkspace(), $this->fetch($file), $file->contentType);

        $this->renditionManager->createOrReplaceRenditionFile(
            $asset,
            $definition,
            $stored,
            null,
            null,
            substituted: $definition->isSubstitutable(),
            force: true,
        );
    }

    private function fetch(ReturnedFile $file): string
    {
        if (null !== $file->url) {
            return $this->downloader->download($file->url);
        }

        $content = base64_decode($file->base64 ?? '', true);
        if (false === $content || '' === $content) {
            throw new EnrichmentFailedException(sprintf('Returned file "%s" carries neither a URL nor base64 content', $file->name));
        }

        $path = tempnam(sys_get_temp_dir(), 'enrichment');
        file_put_contents($path, $content);

        return $path;
    }
}
