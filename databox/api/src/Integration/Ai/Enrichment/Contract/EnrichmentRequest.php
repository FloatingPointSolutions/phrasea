<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

/**
 * Body of POST /enrich, see contracts/enrichment.openapi.yaml.
 */
final readonly class EnrichmentRequest
{
    final public const string FILE_ROLE_ORIGINAL = 'original';
    final public const string FILE_ROLE_PREVIEW = 'preview';

    /**
     * @param string[] $capabilities
     * @param string[] $locales
     */
    public function __construct(
        public string $assetId,
        public string $workspaceId,
        public string $mimeType,
        public string $fileUrl,
        public string $fileRole,
        public array $capabilities,
        public array $locales,
    ) {
    }

    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'workspace_id' => $this->workspaceId,
            'mime_type' => $this->mimeType,
            'file_url' => $this->fileUrl,
            'file_role' => $this->fileRole,
            'capabilities' => array_values($this->capabilities),
            'locales' => array_map(self::toLanguageTag(...), array_values($this->locales)),
        ];
    }

    /**
     * Databox stores locales as "pt_PT", the contract speaks BCP 47 ("pt-PT").
     */
    private static function toLanguageTag(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }
}
