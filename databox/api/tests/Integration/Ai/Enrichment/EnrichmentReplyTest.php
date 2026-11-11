<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ai\Enrichment;

use App\Integration\Ai\Enrichment\Contract\CapabilityList;
use App\Integration\Ai\Enrichment\Contract\Embedding;
use App\Integration\Ai\Enrichment\Contract\EnrichmentError;
use App\Integration\Ai\Enrichment\Contract\EnrichmentReply;
use App\Integration\Ai\Enrichment\Contract\ReturnedFile;
use App\Integration\Ai\Enrichment\Exception\EnrichmentConfigurationException;
use App\Integration\Ai\Enrichment\Exception\EnrichmentFailedException;
use PHPUnit\Framework\TestCase;

class EnrichmentReplyTest extends TestCase
{
    public function testReplyIsParsedFromTheContractShape(): void
    {
        $reply = EnrichmentReply::fromArray([
            'asset_id' => 'asset-1',
            'applied' => ['scene', 'embedding'],
            'suggestions' => [
                ['attribute' => 'caption', 'value' => 'A beach', 'locale' => 'en', 'confidence' => 0.91, 'model' => 'blip', 'model_version' => '2.1'],
                ['attribute' => 'keywords', 'values' => ['beach', 'sea'], 'confidence' => 0.7, 'model' => 'clip', 'model_version' => '1.0', 'concept_id' => 'c-42'],
                ['attribute' => 'location', 'value' => ['lat' => 38.7, 'lon' => -9.1], 'confidence' => 0.6, 'model' => 'geo', 'model_version' => '1'],
            ],
            'files' => [
                ['purpose' => 'rendition', 'content_type' => 'image/webp', 'name' => 'ai_preview', 'url' => 'https://files.example/x.webp'],
            ],
            'embedding' => ['model' => 'clip', 'model_version' => '1.0', 'dimension' => 3, 'vector' => [0.1, 0.2, 0.3]],
        ]);

        $this->assertSame('asset-1', $reply->assetId);
        $this->assertSame(['A beach'], $reply->suggestions[0]->values);
        $this->assertSame('en', $reply->suggestions[0]->locale);
        $this->assertSame(['beach', 'sea'], $reply->suggestions[1]->values);
        $this->assertNull($reply->suggestions[1]->locale);
        $this->assertSame('c-42', $reply->suggestions[1]->conceptId);
        $this->assertSame(['lat' => 38.7, 'lon' => -9.1], $reply->suggestions[2]->values[0]);
        $this->assertSame(ReturnedFile::PURPOSE_RENDITION, $reply->files[0]->purpose);
        $this->assertNull($reply->files[0]->base64);
        $this->assertSame([0.1, 0.2, 0.3], $reply->embedding->vector);
    }

    public function testFilesAndEmbeddingAreOptional(): void
    {
        $reply = EnrichmentReply::fromArray(['asset_id' => 'asset-1', 'applied' => [], 'suggestions' => []]);

        $this->assertSame([], $reply->files);
        $this->assertNull($reply->embedding);
    }

    public function testEmbeddingDimensionMustMatchTheVector(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Embedding::fromArray(['model' => 'clip', 'model_version' => '1.0', 'dimension' => 4, 'vector' => [0.1, 0.2]]);
    }

    public function testCapabilitiesAreFilteredByMimeTypeWildcards(): void
    {
        $capabilities = CapabilityList::fromArray([
            'service' => 'demo',
            'version' => '0.1',
            'capabilities' => [
                ['name' => 'scene', 'mime_types' => ['image/*']],
                ['name' => 'text', 'mime_types' => ['audio/*', 'video/mp4']],
                ['name' => 'embedding'],
            ],
        ]);

        $this->assertSame(['scene', 'embedding'], $capabilities->supporting('image/jpeg'));
        $this->assertSame(['text', 'embedding'], $capabilities->supporting('video/mp4'));
        $this->assertSame(['embedding'], $capabilities->supporting('application/pdf'));
        $this->assertSame(['scene'], $capabilities->supporting('image/png', ['scene', 'people']));
    }

    public function testErrorStatusSelectsTheException(): void
    {
        $body = ['code' => 'unsupported_media_type', 'message' => 'PDF is not supported'];

        $this->assertInstanceOf(EnrichmentConfigurationException::class, EnrichmentError::fromResponse(415, $body)->toException());
        $this->assertInstanceOf(EnrichmentFailedException::class, EnrichmentError::fromResponse(500, $body)->toException());
        $this->assertSame('PDF is not supported (unsupported_media_type)', EnrichmentError::fromResponse(415, $body)->toException()->getMessage());
    }
}
