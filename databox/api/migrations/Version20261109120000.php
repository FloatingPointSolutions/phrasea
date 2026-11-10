<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require a slug on every page, so that no page can take over the application root';
    }

    public function up(Schema $schema): void
    {
        // A UUID is a valid slug, which keeps the former home pages reachable at /p/{id}
        $this->addSql('UPDATE page SET slug = id WHERE slug IS NULL');
        $this->addSql('ALTER TABLE page ALTER slug SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ALTER slug DROP NOT NULL');
    }
}
