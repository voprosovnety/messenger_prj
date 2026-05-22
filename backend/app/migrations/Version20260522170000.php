<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allow_retraction column to poll table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll ADD allow_retraction BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP COLUMN allow_retraction');
    }
}
