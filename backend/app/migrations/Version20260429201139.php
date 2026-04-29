<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429201139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD avatar_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat DROP description');
        $this->addSql('ALTER TABLE "user" DROP avatar_url');
        $this->addSql('ALTER TABLE "user" DROP last_seen_at');
    }
}
