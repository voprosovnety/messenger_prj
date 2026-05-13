<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scheduled_message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE scheduled_message (id UUID NOT NULL, chat_id UUID NOT NULL, sender_id UUID NOT NULL, reply_to_id UUID DEFAULT NULL, content TEXT NOT NULL, attachments JSON DEFAULT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_scheduled_message_chat ON scheduled_message (chat_id)');
        $this->addSql('CREATE INDEX IDX_scheduled_message_sender ON scheduled_message (sender_id)');
        $this->addSql('CREATE INDEX IDX_scheduled_message_reply_to ON scheduled_message (reply_to_id)');
        $this->addSql('CREATE INDEX idx_scheduled_message_due ON scheduled_message (scheduled_at)');
        $this->addSql('CREATE INDEX idx_scheduled_message_chat_sender ON scheduled_message (chat_id, sender_id)');
        $this->addSql('ALTER TABLE scheduled_message ADD CONSTRAINT FK_scheduled_message_chat FOREIGN KEY (chat_id) REFERENCES chat (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scheduled_message ADD CONSTRAINT FK_scheduled_message_sender FOREIGN KEY (sender_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scheduled_message ADD CONSTRAINT FK_scheduled_message_reply_to FOREIGN KEY (reply_to_id) REFERENCES message (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduled_message DROP CONSTRAINT FK_scheduled_message_chat');
        $this->addSql('ALTER TABLE scheduled_message DROP CONSTRAINT FK_scheduled_message_sender');
        $this->addSql('ALTER TABLE scheduled_message DROP CONSTRAINT FK_scheduled_message_reply_to');
        $this->addSql('DROP TABLE scheduled_message');
    }
}
