<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

final readonly class ReturnedFile
{
    final public const string PURPOSE_RENDITION = 'rendition';
    final public const string PURPOSE_TRANSCRIPT = 'transcript';

    public function __construct(
        public string $purpose,
        public string $contentType,
        public ?string $name,
        public ?string $base64,
        public ?string $url,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['purpose'],
            $data['content_type'],
            $data['name'] ?? null,
            $data['base64'] ?? null,
            $data['url'] ?? null,
        );
    }
}
