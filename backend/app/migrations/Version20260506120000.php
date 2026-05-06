<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attachment fields to message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD attachment_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD attachment_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD attachment_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP attachment_url');
        $this->addSql('ALTER TABLE message DROP attachment_type');
        $this->addSql('ALTER TABLE message DROP attachment_name');
    }
}
