<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513143405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avatar_history (id UUID NOT NULL, avatar_url VARCHAR(500) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID DEFAULT NULL, chat_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_avatar_history_user ON avatar_history (user_id)');
        $this->addSql('CREATE INDEX idx_avatar_history_chat ON avatar_history (chat_id)');
        $this->addSql('ALTER TABLE avatar_history ADD CONSTRAINT FK_F1D47F95A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE avatar_history ADD CONSTRAINT FK_F1D47F951A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avatar_history DROP CONSTRAINT FK_F1D47F95A76ED395');
        $this->addSql('ALTER TABLE avatar_history DROP CONSTRAINT FK_F1D47F951A9A7125');
        $this->addSql('DROP TABLE avatar_history');
    }
}
