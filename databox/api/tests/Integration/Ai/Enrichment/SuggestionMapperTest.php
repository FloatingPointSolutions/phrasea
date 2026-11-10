<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ai\Enrichment;

use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Integration\Ai\Enrichment\AiEnrichmentIntegration;
use App\Integration\Ai\Enrichment\Contract\AttributeSuggestion;
use App\Integration\Ai\Enrichment\SuggestionMapper;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class SuggestionMapperTest extends TestCase
{
    private const string WORKSPACE_ID = 'ce9899b7-2513-44b7-9c12-f6cf0f052b9f';
    private const array MAPPING = [
        'keywords' => ['attribute' => 'keywords', 'threshold' => 0.5],
        'caption' => ['attribute' => 'caption'],
        'description' => ['attribute' => 'description'],
    ];

    /**
     * @var array<string, AttributeDefinition> definitions of the workspace, indexed by slug
     */
    private array $definitions = [];

    protected function setUp(): void
    {
        $this->definitions = [];
    }

    public function testMultiValuedSuggestionDeletesOwnValuesThenAddsEachValue(): void
    {
        $keywords = $this->createDefinition('keywords', multiple: true);

        $actions = $this->map([$this->suggest('keywords', ['beach', 'sunset'], confidence: 0.9)]);

        $this->assertSame([
            BatchAttributeManager::ACTION_DELETE,
            BatchAttributeManager::ACTION_ADD,
            BatchAttributeManager::ACTION_ADD,
        ], $this->names($actions));
        $this->assertSame(['beach', 'sunset'], [$actions[1]->value, $actions[2]->value]);
        $this->assertSame(0.9, $actions[1]->confidence);
        $this->assertSame($keywords->getId(), $actions[0]->definitionId);
    }

    public function testDeleteIsScopedToTheIntegrationModelAndVersion(): void
    {
        $this->createDefinition('keywords', multiple: true);

        $delete = $this->map([$this->suggest('keywords', ['beach'])])[0];

        $this->assertSame(Attribute::ORIGIN_MACHINE, $delete->origin);
        $this->assertSame(AiEnrichmentIntegration::getName(), $delete->originVendor);
        $this->assertSame('clip', $delete->model);
        $this->assertSame('1.0', $delete->modelVersion);
        $this->assertNull($delete->id);
    }

    public function testSameModelAndVersionShareOneDeletePerDefinition(): void
    {
        $this->createDefinition('keywords', multiple: true);

        $actions = $this->map([
            $this->suggest('keywords', ['beach']),
            $this->suggest('keywords', ['sunset']),
            $this->suggest('keywords', ['sea'], modelVersion: '2.0'),
        ]);

        $deletes = array_filter($actions, fn (AttributeActionInput $a): bool => BatchAttributeManager::ACTION_DELETE === $a->action);
        $this->assertSame(['1.0', '2.0'], array_values(array_map(fn (AttributeActionInput $a): ?string => $a->modelVersion, $deletes)));
        $this->assertCount(5, $actions);
    }

    public function testValuesBelowTheThresholdAreDropped(): void
    {
        $this->createDefinition('keywords', multiple: true);

        $this->assertSame([], $this->map([$this->suggest('keywords', ['beach'], confidence: 0.3)]));
    }

    public function testUnmappedServiceAttributesAreIgnored(): void
    {
        $this->createDefinition('keywords', multiple: true);

        $this->assertSame([], $this->map([$this->suggest('objects', ['car'])]));
    }

    public function testSingleValuedSuggestionSetsTheFirstValue(): void
    {
        $this->createDefinition('caption');

        $actions = $this->map([$this->suggest('caption', ['A beach at sunset', 'Alternative'])]);

        $this->assertSame([BatchAttributeManager::ACTION_DELETE, BatchAttributeManager::ACTION_SET], $this->names($actions));
        $this->assertSame('A beach at sunset', $actions[1]->value);
        $this->assertNull($actions[1]->locale);
    }

    public function testSingleValuedHumanValueIsNeverOverwritten(): void
    {
        $caption = $this->createDefinition('caption');

        $actions = $this->map(
            [$this->suggest('caption', ['A beach at sunset'])],
            [$this->existing($caption, null, 'ai.enrichment'), $this->existing($caption, null, null)],
        );

        $this->assertSame([BatchAttributeManager::ACTION_DELETE], $this->names($actions));
    }

    public function testSingleValuedOwnValueIsOverwritten(): void
    {
        $caption = $this->createDefinition('caption');

        $actions = $this->map(
            [$this->suggest('caption', ['A beach at sunset'])],
            [$this->existing($caption, null, 'ai.enrichment')],
        );

        $this->assertSame([BatchAttributeManager::ACTION_DELETE, BatchAttributeManager::ACTION_SET], $this->names($actions));
    }

    public function testHumanValueProtectionIsPerLocale(): void
    {
        $description = $this->createDefinition('description', translatable: true);

        $actions = $this->map(
            [$this->suggest('description', ['Praia'], locale: 'pt'), $this->suggest('description', ['Beach'], locale: 'en')],
            [$this->existing($description, 'pt', null)],
        );

        $this->assertSame([BatchAttributeManager::ACTION_DELETE, BatchAttributeManager::ACTION_SET], $this->names($actions));
        $this->assertSame('en', $actions[1]->locale);
    }

    public function testNonTranslatableDefinitionKeepsOnlyThePrimaryLocale(): void
    {
        $this->createDefinition('caption');

        $actions = $this->map([
            $this->suggest('caption', ['Praia'], locale: 'pt'),
            $this->suggest('caption', ['Beach'], locale: 'en'),
        ]);

        $this->assertSame(['Beach'], [$actions[1]->value]);
        $this->assertCount(2, $actions);
    }

    public function testLanguageTagsAreNormalizedToDataboxLocales(): void
    {
        $description = $this->createDefinition('description', translatable: true);

        $actions = $this->map(
            [$this->suggest('description', ['Praia'], locale: 'pt-PT'), $this->suggest('description', ['Playa'], locale: 'es-ES')],
            [$this->existing($description, 'es_ES', null)],
        );

        $this->assertSame([BatchAttributeManager::ACTION_DELETE, BatchAttributeManager::ACTION_SET], $this->names($actions));
        $this->assertSame('pt_PT', $actions[1]->locale);
    }

    public function testTranslatableDefinitionWithoutLocaleUsesThePrimaryLocale(): void
    {
        $this->createDefinition('description', translatable: true);

        $actions = $this->map([$this->suggest('description', ['Beach'])]);

        $this->assertSame('en', $actions[1]->locale);
    }

    /**
     * @param AttributeSuggestion[] $suggestions
     * @param Attribute[]           $existing
     *
     * @return AttributeActionInput[]
     */
    private function map(array $suggestions, array $existing = []): array
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getWorkspaceId')->willReturn(self::WORKSPACE_ID);
        $asset->method('getAttributes')->willReturn(new ArrayCollection($existing));

        $repository = $this->createMock(AttributeDefinitionRepository::class);
        $repository->method('getAttributeDefinitionBySlug')
            ->willReturnCallback(fn (string $workspaceId, string $slug): ?AttributeDefinition => $this->definitions[$slug] ?? null);

        return (new SuggestionMapper($repository))->map($asset, self::MAPPING, $suggestions, 'en')->actions;
    }

    /**
     * @param AttributeActionInput[] $actions
     *
     * @return string[]
     */
    private function names(array $actions): array
    {
        return array_map(fn (AttributeActionInput $a): ?string => $a->action, $actions);
    }

    private function suggest(string $attribute, array $values, ?string $locale = null, float $confidence = 0.8, string $modelVersion = '1.0'): AttributeSuggestion
    {
        return new AttributeSuggestion($attribute, $values, $locale, null, $confidence, 'clip', $modelVersion);
    }

    private function existing(AttributeDefinition $definition, ?string $locale, ?string $originVendor): Attribute
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getDefinition')->willReturn($definition);
        $attribute->method('getLocale')->willReturn($locale);
        $attribute->method('getOriginVendor')->willReturn($originVendor);

        return $attribute;
    }

    private function createDefinition(string $slug, bool $multiple = false, bool $translatable = false): AttributeDefinition
    {
        $definition = $this->createMock(AttributeDefinition::class);
        $definition->method('getId')->willReturn(Uuid::uuid4()->toString());
        $definition->method('getSlug')->willReturn($slug);
        $definition->method('isMultiple')->willReturn($multiple);
        $definition->method('isTranslatable')->willReturn($translatable);
        $definition->method('getWorkspaceId')->willReturn(self::WORKSPACE_ID);

        return $this->definitions[$slug] = $definition;
    }
}
