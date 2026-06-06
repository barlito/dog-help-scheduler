<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606121627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when a postponed notification will repop (notification.postponed_until)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD postponed_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP postponed_until');
    }
}
