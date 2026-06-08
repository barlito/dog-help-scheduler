<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Timestampable (Barlito\\Utils\\Traits\\TimestampableTrait): add updated_at to notification '
            . '(which already had created_at) and created_at/updated_at to notification_type and settings.';
    }

    public function up(Schema $schema): void
    {
        // Add NOT NULL columns with a default so existing rows are backfilled, then drop
        // the default to match the entity (values come from the Gedmo listener at runtime).
        $this->addSql('ALTER TABLE notification ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE notification ALTER COLUMN updated_at DROP DEFAULT');

        $this->addSql('ALTER TABLE notification_type ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE notification_type ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE notification_type ALTER COLUMN created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE notification_type ALTER COLUMN updated_at DROP DEFAULT');

        $this->addSql('ALTER TABLE settings ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE settings ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE settings ALTER COLUMN created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE settings ALTER COLUMN updated_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP updated_at');
        $this->addSql('ALTER TABLE notification_type DROP created_at');
        $this->addSql('ALTER TABLE notification_type DROP updated_at');
        $this->addSql('ALTER TABLE settings DROP created_at');
        $this->addSql('ALTER TABLE settings DROP updated_at');
    }
}
