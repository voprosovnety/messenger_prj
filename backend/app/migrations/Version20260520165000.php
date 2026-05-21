<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_pinned column to chat_member table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_member ADD is_pinned BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_member DROP is_pinned');
    }
}
