<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Exception;

/**
 * This asset could not be enriched (unreachable file, overloaded service): the job fails, the integration stays enabled.
 */
final class EnrichmentFailedException extends \RuntimeException
{
}
