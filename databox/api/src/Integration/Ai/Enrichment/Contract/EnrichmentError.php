<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Contract;

use App\Integration\Ai\Enrichment\Exception\EnrichmentConfigurationException;
use App\Integration\Ai\Enrichment\Exception\EnrichmentFailedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Error body of the service, turned into the exception matching its cause.
 */
final readonly class EnrichmentError
{
    /**
     * Statuses the contract reserves for a mismatch between the integration configuration and the service.
     */
    private const array CONFIGURATION_STATUSES = [
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED,
        Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
    ];

    public function __construct(
        public int $status,
        public string $code,
        public string $message,
        public ?array $details,
    ) {
    }

    public static function fromResponse(int $status, array $body): self
    {
        return new self(
            $status,
            $body['code'] ?? 'unknown',
            $body['message'] ?? sprintf('Enrichment service answered with status %d', $status),
            $body['details'] ?? null,
        );
    }

    public function toException(): \Exception
    {
        $message = sprintf('%s (%s)', $this->message, $this->code);

        return in_array($this->status, self::CONFIGURATION_STATUSES, true)
            ? new EnrichmentConfigurationException($message, $this->status)
            : new EnrichmentFailedException($message, $this->status);
    }
}
