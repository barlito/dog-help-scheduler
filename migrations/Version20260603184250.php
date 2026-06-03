<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603184250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-type postpone delay (notification_type.postpone_minutes)';
    }

    public function up(Schema $schema): void
    {
        // Add with a default so the existing seeded row is backfilled, then drop the
        // default to match the entity (PHP-level default only).
        $this->addSql('ALTER TABLE notification_type ADD postpone_minutes INT NOT NULL DEFAULT 10');
        $this->addSql('ALTER TABLE notification_type ALTER COLUMN postpone_minutes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_type DROP postpone_minutes');
    }
}
