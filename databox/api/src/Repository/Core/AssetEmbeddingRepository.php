<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AssetEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetEmbedding::class);
    }

    /**
     * @param float[] $vector
     */
    public function replace(Asset $asset, string $model, string $modelVersion, array $vector): AssetEmbedding
    {
        $embedding = $this->findOneBy([
            'asset' => $asset->getId(),
            'model' => $model,
            'modelVersion' => $modelVersion,
        ]) ?? new AssetEmbedding($asset, $model, $modelVersion);

        $embedding->setVector($vector);
        $this->getEntityManager()->persist($embedding);

        return $embedding;
    }
}
