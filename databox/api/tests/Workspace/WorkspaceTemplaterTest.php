<?php

declare(strict_types=1);

namespace App\Tests\Workspace;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\EntityList;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Model\AssetTypeEnum;
use App\Service\Workspace\WorkspaceTemplater;
use App\Tests\AbstractDataboxTestCase;

class WorkspaceTemplaterTest extends AbstractDataboxTestCase
{
    public function testTemplateRoundTripIsIdempotent(): void
    {
        $em = self::getEntityManager();
        $source = $this->createWorkspace(['name' => 'Source']);

        $list = new EntityList();
        $list->setWorkspace($source);
        $list->setOwnerId($source->getOwnerId());
        $list->setName('Countries');
        $em->persist($list);
        $this->createAttributeDefinition([
            'workspace' => $source,
            'policy' => $em->getRepository(AttributePolicy::class)->findOneBy(['workspace' => $source]),
            'name' => 'Country',
            'list' => $list,
            'no_flush' => true,
        ]);

        $thumbnail = $em->getRepository(RenditionDefinition::class)->findOneBy(['workspace' => $source, 'key' => 'thumbnail']);
        $baseRenditions = new WorkspaceIntegration();
        $baseRenditions->setWorkspace($source);
        $baseRenditions->setOwnerId($source->getOwnerId());
        $baseRenditions->setName('Base Renditions');
        $baseRenditions->setIntegration(RenditionIntegration::getName());
        $baseRenditions->setPublic(true);
        $baseRenditions->setConfig(['renditions' => [$thumbnail->getId()]]);
        $em->persist($baseRenditions);
        $this->findIntegration($source, RenditionIntegration::getName())->addNeed($baseRenditions);
        $em->flush();

        /** @var WorkspaceTemplater $templater */
        $templater = self::getService(WorkspaceTemplater::class);
        $template = $templater->export($source);

        self::assertSame('Source', $template['Workspace']['name']);
        self::assertSame(['main', 'preview', 'thumbnail'], array_column($template['RenditionDefinition'], 'key'));
        self::assertSame(AssetTypeEnum::Both->value, $template['RenditionDefinition'][0]['target']);
        $exportedIntegrations = array_column($template['WorkspaceIntegration'], null, 'id');
        self::assertSame(['#Thumbnail'], $exportedIntegrations[$baseRenditions->getId()]['config']['renditions']);
        self::assertSame(
            [$baseRenditions->getId()],
            $exportedIntegrations[$this->findIntegration($source, RenditionIntegration::getName())->getId()]['needs']
        );

        $templater->import($template, 'Target', 'target', $source->getOwnerId());
        $templater->import($template, 'Target', 'target', $source->getOwnerId());
        $em->clear();

        $target = $em->getRepository(Workspace::class)->findOneBy(['name' => 'Target']);
        self::assertCount(3, $em->getRepository(RenditionDefinition::class)->findBy(['workspace' => $target]));
        self::assertCount(1, $em->getRepository(EntityList::class)->findBy(['workspace' => $target]));
        self::assertCount(3, $em->getRepository(WorkspaceIntegration::class)->findBy(['workspace' => $target]));

        $country = $em->getRepository(AttributeDefinition::class)->findOneBy(['workspace' => $target, 'name' => 'Country']);
        self::assertSame('Countries', $country->getEntityList()->getName());

        $targetThumbnail = $em->getRepository(RenditionDefinition::class)->findOneBy(['workspace' => $target, 'key' => 'thumbnail']);
        self::assertSame(AssetTypeEnum::Both, $targetThumbnail->getTarget());

        $targetBaseRenditions = $this->findIntegration($target, RenditionIntegration::getName(), 'Base Renditions');
        self::assertSame([$targetThumbnail->getId()], $targetBaseRenditions->getConfig()['renditions']);
        self::assertSame(
            [$targetBaseRenditions->getId()],
            $this->findIntegration($target, RenditionIntegration::getName())->getNeeds()
                ->map(fn (WorkspaceIntegration $need): string => $need->getId())
                ->getValues()
        );
    }

    private function findIntegration(Workspace $workspace, string $integration, ?string $name = null): WorkspaceIntegration
    {
        return self::getEntityManager()->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $workspace,
            'integration' => $integration,
            'name' => $name,
        ]);
    }
}
