<?php

declare(strict_types=1);

namespace App\Integration\Ai\Enrichment;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Entity\Core\AttributeDefinition;
use App\Integration\Ai\Enrichment\Contract\AttributeSuggestion;
use App\Repository\Core\AttributeDefinitionRepository;

/**
 * Turns service suggestions into attribute actions through the configured mapping.
 *
 * Every (definition, model, version) group first deletes the values this
 * integration wrote with that same model, so re-running is idempotent; values
 * written by anyone else are never touched.
 */
final readonly class SuggestionMapper
{
    public function __construct(
        private AttributeDefinitionRepository $definitionRepository,
    ) {
    }

    /**
     * @param array<string, array{attribute: string, threshold?: float|null}> $mapping      indexed by service attribute name
     * @param AttributeSuggestion[]                                            $suggestions
     */
    public function map(Asset $asset, array $mapping, array $suggestions, string $primaryLocale): AssetAttributeBatchUpdateInput
    {
        $input = new AssetAttributeBatchUpdateInput();
        $input->actions = [];
        $cleared = [];
        $primaryLocale = LocaleUtil::normalizeLocale($primaryLocale);

        foreach ($suggestions as $suggestion) {
            $target = $mapping[$suggestion->attribute] ?? null;
            if (null === $target || $suggestion->confidence < ($target['threshold'] ?? 0.0)) {
                continue;
            }

            $definition = $this->definitionRepository->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $target['attribute']);
            $suggestedLocale = null !== $suggestion->locale ? LocaleUtil::normalizeLocale($suggestion->locale) : null;
            if (null === $definition || !$this->acceptsLocale($definition, $suggestedLocale, $primaryLocale)) {
                continue;
            }

            $locale = $definition->isTranslatable() ? $suggestedLocale ?? $primaryLocale : null;
            $group = implode("\0", [$definition->getId(), $suggestion->model, $suggestion->modelVersion]);
            if (!isset($cleared[$group])) {
                $cleared[$group] = true;
                $input->actions[] = $this->createAction(BatchAttributeManager::ACTION_DELETE, $definition, $suggestion);
            }

            if ($definition->isMultiple()) {
                foreach ($suggestion->values as $value) {
                    $input->actions[] = $this->createValueAction(BatchAttributeManager::ACTION_ADD, $definition, $suggestion, $value, $locale);
                }
            } elseif (!$this->isHeldByOthers($asset, $definition, $locale)) {
                $input->actions[] = $this->createValueAction(BatchAttributeManager::ACTION_SET, $definition, $suggestion, $suggestion->values[0], $locale);
            }
        }

        return $input;
    }

    private function acceptsLocale(AttributeDefinition $definition, ?string $locale, string $primaryLocale): bool
    {
        return $definition->isTranslatable() || null === $locale || $locale === $primaryLocale;
    }

    private function isHeldByOthers(Asset $asset, AttributeDefinition $definition, ?string $locale): bool
    {
        return $asset->getAttributes()->exists(
            fn ($key, Attribute $attribute): bool => $attribute->getDefinition()?->getId() === $definition->getId()
                && $attribute->getLocale() === $locale
                && AiEnrichmentIntegration::getName() !== $attribute->getOriginVendor()
        );
    }

    private function createValueAction(string $action, AttributeDefinition $definition, AttributeSuggestion $suggestion, mixed $value, ?string $locale): AttributeActionInput
    {
        $input = $this->createAction($action, $definition, $suggestion);
        $input->value = $value;
        $input->locale = $locale;
        $input->confidence = $suggestion->confidence;

        return $input;
    }

    private function createAction(string $action, AttributeDefinition $definition, AttributeSuggestion $suggestion): AttributeActionInput
    {
        $input = new AttributeActionInput();
        $input->action = $action;
        $input->definitionId = $definition->getId();
        $input->origin = Attribute::ORIGIN_MACHINE;
        $input->originVendor = AiEnrichmentIntegration::getName();
        $input->model = $suggestion->model;
        $input->modelVersion = $suggestion->modelVersion;

        return $input;
    }
}
