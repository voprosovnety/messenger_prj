<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316095529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, is_group BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE chat_member (id UUID NOT NULL, role VARCHAR(20) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, chat_id UUID NOT NULL, member_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1738CD591A9A7125 ON chat_member (chat_id)');
        $this->addSql('CREATE INDEX IDX_1738CD597597D3FE ON chat_member (member_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_chat_member ON chat_member (chat_id, member_id)');
        $this->addSql('ALTER TABLE chat_member ADD CONSTRAINT FK_1738CD591A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chat_member ADD CONSTRAINT FK_1738CD597597D3FE FOREIGN KEY (member_id) REFERENCES "user" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_member DROP CONSTRAINT FK_1738CD591A9A7125');
        $this->addSql('ALTER TABLE chat_member DROP CONSTRAINT FK_1738CD597597D3FE');
        $this->addSql('DROP TABLE chat');
        $this->addSql('DROP TABLE chat_member');
    }
}
