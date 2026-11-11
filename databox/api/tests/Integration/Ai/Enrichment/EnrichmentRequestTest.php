<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ai\Enrichment;

use App\Integration\Ai\Enrichment\Contract\EnrichmentRequest;
use PHPUnit\Framework\TestCase;

class EnrichmentRequestTest extends TestCase
{
    public function testBodyFollowsTheContractShape(): void
    {
        $request = new EnrichmentRequest(
            'asset-1',
            'workspace-1',
            'image/jpeg',
            'https://files.example/preview.jpg',
            EnrichmentRequest::FILE_ROLE_PREVIEW,
            ['caption', 'embedding'],
            ['pt_PT', 'en'],
        );

        $this->assertSame([
            'asset_id' => 'asset-1',
            'workspace_id' => 'workspace-1',
            'mime_type' => 'image/jpeg',
            'file_url' => 'https://files.example/preview.jpg',
            'file_role' => 'preview',
            'capabilities' => ['caption', 'embedding'],
            'locales' => ['pt-PT', 'en'],
        ], $request->toArray());
    }
}
