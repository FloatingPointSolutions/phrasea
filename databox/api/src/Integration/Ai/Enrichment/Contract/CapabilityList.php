<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

/**
 * Answer of GET /capabilities: what a service offers and for which MIME types.
 */
final readonly class CapabilityList
{
    /**
     * @param array<string, string[]|null> $mimeTypes accepted MIME types ("type/*" allowed) indexed by capability name, null when every type is accepted
     */
    public function __construct(
        public string $service,
        public string $version,
        public array $mimeTypes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $mimeTypes = [];
        foreach ($data['capabilities'] as $capability) {
            $mimeTypes[$capability['name']] = $capability['mime_types'] ?? null;
        }

        return new self($data['service'], $data['version'], $mimeTypes);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->mimeTypes);
    }

    /**
     * Capabilities accepting the given MIME type, among the requested ones (all of them when none is requested).
     *
     * @param string[]|null $requested
     *
     * @return string[]
     */
    public function supporting(string $mimeType, ?array $requested = null): array
    {
        $names = null === $requested ? $this->getNames() : array_intersect($requested, $this->getNames());

        return array_values(array_filter($names, fn (string $name): bool => $this->accepts($name, $mimeType)));
    }

    private function accepts(string $capability, string $mimeType): bool
    {
        $accepted = $this->mimeTypes[$capability];
        if (null === $accepted) {
            return true;
        }

        return array_any($accepted, fn (string $pattern): bool => fnmatch($pattern, $mimeType));
    }
}
