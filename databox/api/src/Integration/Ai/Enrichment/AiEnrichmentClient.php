<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use App\Integration\Ai\Enrichment\Contract\CapabilityList;
use App\Integration\Ai\Enrichment\Contract\EnrichmentError;
use App\Integration\Ai\Enrichment\Contract\EnrichmentReply;
use App\Integration\Ai\Enrichment\Contract\EnrichmentRequest;
use App\Integration\IntegrationConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class AiEnrichmentClient
{
    /**
     * 503 is the only reply the contract allows the worker to retry, after the announced delay.
     * The delay is capped because it blocks the worker holding the job.
     */
    private const int MAX_ATTEMPTS = 3;
    private const int MAX_RETRY_DELAY = 10;
    private const int CAPABILITIES_TTL = 3600;

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
    ) {
    }

    public function getCapabilities(IntegrationConfig $config): CapabilityList
    {
        $key = 'ai_enrichment.capabilities.'.hash('xxh128', $config['endpoint']);

        return $this->cache->get($key, function (ItemInterface $item) use ($config): CapabilityList {
            $item->expiresAfter(self::CAPABILITIES_TTL);

            return CapabilityList::fromArray($this->request($config, 'GET', '/capabilities'));
        });
    }

    public function enrich(IntegrationConfig $config, EnrichmentRequest $request): EnrichmentReply
    {
        return EnrichmentReply::fromArray($this->request($config, 'POST', '/enrich', [
            'json' => $request->toArray(),
        ]));
    }

    private function request(IntegrationConfig $config, string $method, string $path, array $options = []): array
    {
        $options += [
            'auth_bearer' => $config['token'],
            'timeout' => $config['timeout'],
            'max_duration' => $config['timeout'],
        ];
        $url = rtrim((string) $config['endpoint'], '/').$path;

        for ($attempt = 1; ; ++$attempt) {
            $response = $this->client->request($method, $url, $options);
            $status = $response->getStatusCode();

            if (Response::HTTP_SERVICE_UNAVAILABLE === $status && $attempt < self::MAX_ATTEMPTS) {
                $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 1);
                sleep(max(1, min($retryAfter, self::MAX_RETRY_DELAY)));
                continue;
            }

            if ($status >= Response::HTTP_BAD_REQUEST) {
                throw EnrichmentError::fromResponse($status, $this->decodeErrorBody($response))->toException();
            }

            return $response->toArray();
        }
    }

    /**
     * Proxies and gateways answer errors with HTML or an empty body, which the contract error shape must tolerate.
     */
    private function decodeErrorBody(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return [];
        }
    }
}
