<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forwarded_from column to message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD forwarded_from VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP COLUMN forwarded_from');
    }
}
