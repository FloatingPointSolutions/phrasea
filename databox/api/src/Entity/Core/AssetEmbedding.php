<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\AssetEmbeddingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vector representation of an asset computed by an enrichment model.
 * One row per asset and model so that several models can coexist until a vector index consumes them.
 */
#[ORM\Entity(repositoryClass: AssetEmbeddingRepository::class)]
#[ORM\UniqueConstraint(columns: ['asset_id', 'model', 'model_version'])]
class AssetEmbedding extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $model;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $modelVersion;

    #[ORM\Column(type: Types::INTEGER)]
    private int $dimension;

    /**
     * @var float[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $vector;

    public function __construct(Asset $asset, string $model, string $modelVersion)
    {
        parent::__construct();
        $this->asset = $asset;
        $this->model = $model;
        $this->modelVersion = $modelVersion;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * @return float[]
     */
    public function getVector(): array
    {
        return $this->vector;
    }

    /**
     * @param float[] $vector
     */
    public function setVector(array $vector): void
    {
        $this->vector = array_values($vector);
        $this->dimension = count($vector);
    }
}
