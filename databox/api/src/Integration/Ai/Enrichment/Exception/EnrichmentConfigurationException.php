<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment\Exception;

/**
 * The service rejects what the integration is configured to send: the workspace integration is notified and disabled.
 */
final class EnrichmentConfigurationException extends \InvalidArgumentException
{
}
