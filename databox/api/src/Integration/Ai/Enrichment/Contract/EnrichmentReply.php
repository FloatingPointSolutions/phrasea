<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

final readonly class EnrichmentReply
{
    /**
     * @param string[]              $applied
     * @param AttributeSuggestion[] $suggestions
     * @param ReturnedFile[]        $files
     */
    public function __construct(
        public string $assetId,
        public array $applied,
        public array $suggestions,
        public array $files,
        public ?Embedding $embedding,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['asset_id'],
            array_values($data['applied']),
            array_map(AttributeSuggestion::fromArray(...), array_values($data['suggestions'])),
            array_map(ReturnedFile::fromArray(...), array_values($data['files'] ?? [])),
            isset($data['embedding']) ? Embedding::fromArray($data['embedding']) : null,
        );
    }
}
