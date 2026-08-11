<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810145500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use timestampable fields on categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE category SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE category CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP updated_at');
    }
}
