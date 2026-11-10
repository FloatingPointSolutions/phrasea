<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

final readonly class AttributeSuggestion
{
    /**
     * @param list<mixed> $values every proposed value, from "values" or the single "value"
     */
    public function __construct(
        public string $attribute,
        public array $values,
        public ?string $locale,
        public ?string $conceptId,
        public float $confidence,
        public string $model,
        public string $modelVersion,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['attribute'],
            array_key_exists('values', $data) ? array_values($data['values']) : [$data['value']],
            $data['locale'] ?? null,
            $data['concept_id'] ?? null,
            (float) $data['confidence'],
            $data['model'],
            $data['model_version'],
        );
    }
}
