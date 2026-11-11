<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the model behind machine attribute values and store asset embeddings produced by enrichment services';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute ADD model VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute ADD model_version VARCHAR(100) DEFAULT NULL');

        $this->addSql('CREATE TABLE asset_embedding (id UUID NOT NULL, asset_id UUID NOT NULL, model VARCHAR(255) NOT NULL, model_version VARCHAR(100) NOT NULL, dimension INT NOT NULL, vector JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A561BD945DA1941 ON asset_embedding (asset_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A561BD945DA1941D79572D9DF8FBA32 ON asset_embedding (asset_id, model, model_version)');
        $this->addSql('COMMENT ON COLUMN asset_embedding.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.asset_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN asset_embedding.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE asset_embedding ADD CONSTRAINT FK_A561BD945DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE asset_embedding');
        $this->addSql('ALTER TABLE attribute DROP model_version');
        $this->addSql('ALTER TABLE attribute DROP model');
    }
}
