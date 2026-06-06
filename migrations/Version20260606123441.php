<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606123441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-type random jitter added to the postpone delay (notification_type.postpone_jitter_max_minutes)';
    }

    public function up(Schema $schema): void
    {
        // Add with a default so the existing rows are backfilled, then drop the
        // default to match the entity (PHP-level default only).
        $this->addSql('ALTER TABLE notification_type ADD postpone_jitter_max_minutes INT NOT NULL DEFAULT 5');
        $this->addSql('ALTER TABLE notification_type ALTER COLUMN postpone_jitter_max_minutes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_type DROP postpone_jitter_max_minutes');
    }
}
