<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513101404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message_reaction (id UUID NOT NULL, emoji VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, message_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_ADF1C3E6537A1329 ON message_reaction (message_id)');
        $this->addSql('CREATE INDEX IDX_ADF1C3E6A76ED395 ON message_reaction (user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_reaction ON message_reaction (message_id, user_id, emoji)');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message_reaction DROP CONSTRAINT FK_ADF1C3E6537A1329');
        $this->addSql('ALTER TABLE message_reaction DROP CONSTRAINT FK_ADF1C3E6A76ED395');
        $this->addSql('DROP TABLE message_reaction');
    }
}
