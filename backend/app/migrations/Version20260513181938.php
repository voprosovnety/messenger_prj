<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513181938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pinned_message (id UUID NOT NULL, pinned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, chat_id UUID NOT NULL, message_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E0E509EB1A9A7125 ON pinned_message (chat_id)');
        $this->addSql('CREATE INDEX IDX_E0E509EB537A1329 ON pinned_message (message_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_chat_message_pin ON pinned_message (chat_id, message_id)');
        $this->addSql('ALTER TABLE pinned_message ADD CONSTRAINT FK_E0E509EB1A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pinned_message ADD CONSTRAINT FK_E0E509EB537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chat DROP CONSTRAINT fk_659df2aac254572f');
        $this->addSql('DROP INDEX idx_659df2aac254572f');
        $this->addSql('ALTER TABLE chat DROP pinned_message_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pinned_message DROP CONSTRAINT FK_E0E509EB1A9A7125');
        $this->addSql('ALTER TABLE pinned_message DROP CONSTRAINT FK_E0E509EB537A1329');
        $this->addSql('DROP TABLE pinned_message');
        $this->addSql('ALTER TABLE chat ADD pinned_message_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT fk_659df2aac254572f FOREIGN KEY (pinned_message_id) REFERENCES message (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_659df2aac254572f ON chat (pinned_message_id)');
    }
}
