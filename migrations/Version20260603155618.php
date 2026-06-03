<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make notification types configurable: introduce the notification_type table,
 * seed the default "fake walk" type, and link existing notifications to it.
 */
final class Version20260603155618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configurable notification types (notification_type + notification.type_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_type (id UUID NOT NULL, key VARCHAR(64) NOT NULL, label VARCHAR(120) NOT NULL, title VARCHAR(200) NOT NULL, message TEXT NOT NULL, tags JSON NOT NULL, window_start VARCHAR(5) NOT NULL, window_end VARCHAR(5) NOT NULL, per_day INT NOT NULL, min_gap_minutes INT NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_34E21C138A90ABA9 ON notification_type (key)');

        // Seed the default type so existing behaviour is preserved out of the box.
        $this->addSql(<<<'SQL'
            INSERT INTO notification_type (id, key, label, title, message, tags, window_start, window_end, per_day, min_gap_minutes, enabled)
            VALUES (
                gen_random_uuid(), 'fake_walk', 'Fausse sortie', '🐕 Fausse sortie',
                'C''est l''heure d''une fausse sortie pour aider loulou à rester calme seul. Tu l''as faite ?',
                '["dog2","walking"]', '08:00', '20:00', 4, 60, true
            )
            SQL);

        // Link existing notifications to the default type, then enforce the FK.
        $this->addSql('ALTER TABLE notification ADD type_id UUID DEFAULT NULL');
        $this->addSql("UPDATE notification SET type_id = (SELECT id FROM notification_type WHERE key = 'fake_walk') WHERE type_id IS NULL");
        $this->addSql('ALTER TABLE notification ALTER type_id SET NOT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAC54C8C93 FOREIGN KEY (type_id) REFERENCES notification_type (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BF5476CAC54C8C93 ON notification (type_id)');
        $this->addSql('ALTER TABLE notification DROP type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAC54C8C93');
        $this->addSql('DROP INDEX IDX_BF5476CAC54C8C93');
        $this->addSql("ALTER TABLE notification ADD type VARCHAR(32) DEFAULT 'fake_walk' NOT NULL");
        $this->addSql('ALTER TABLE notification DROP type_id');
        $this->addSql('DROP TABLE notification_type');
    }
}
