<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

final readonly class Embedding
{
    /**
     * @param float[] $vector
     */
    public function __construct(
        public string $model,
        public string $modelVersion,
        public array $vector,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $vector = array_map(floatval(...), array_values($data['vector']));
        if (count($vector) !== (int) $data['dimension']) {
            throw new \InvalidArgumentException(sprintf('Embedding of model "%s" announces %d dimensions but carries %d values', $data['model'], $data['dimension'], count($vector)));
        }

        return new self($data['model'], $data['model_version'], $vector);
    }
}
