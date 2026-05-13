<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add poll and poll_vote tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE poll (id UUID NOT NULL, message_id UUID NOT NULL, question TEXT NOT NULL, options JSON NOT NULL, multiple_answers BOOLEAN NOT NULL DEFAULT false, anonymous BOOLEAN NOT NULL DEFAULT false, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_poll_message ON poll (message_id)');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_poll_message FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE poll_vote (id UUID NOT NULL, poll_id UUID NOT NULL, user_id UUID NOT NULL, option_index INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_poll_vote_poll ON poll_vote (poll_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_poll_vote ON poll_vote (poll_id, user_id, option_index)');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_poll_vote_poll FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_poll_vote_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll_vote DROP CONSTRAINT FK_poll_vote_poll');
        $this->addSql('ALTER TABLE poll_vote DROP CONSTRAINT FK_poll_vote_user');
        $this->addSql('DROP TABLE poll_vote');
        $this->addSql('ALTER TABLE poll DROP CONSTRAINT FK_poll_message');
        $this->addSql('DROP TABLE poll');
    }
}
