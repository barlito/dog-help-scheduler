<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603170557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Single-row settings (editable ntfy topic)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id UUID NOT NULL, ntfy_topic VARCHAR(120) NOT NULL, PRIMARY KEY (id))');
        // Seed the single row (empty topic = fall back to the NTFY_TOPIC env var).
        $this->addSql("INSERT INTO settings (id, ntfy_topic) VALUES (gen_random_uuid(), '')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE settings');
    }
}
