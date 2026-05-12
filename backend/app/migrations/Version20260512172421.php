<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512172421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.type column; make sender nullable for system messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD type VARCHAR(10) DEFAULT 'text' NOT NULL");
        $this->addSql('ALTER TABLE message ALTER sender_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP type');
        $this->addSql('ALTER TABLE message ALTER sender_id SET NOT NULL');
    }
}
