<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event store, read model, and messenger tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS events (id SERIAL PRIMARY KEY, uuid UUID NOT NULL, playhead INT NOT NULL, metadata JSON NOT NULL, payload JSON NOT NULL, recorded_on TIMESTAMP NOT NULL, type VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_uuid_playhead ON events (uuid, playhead)');

        $this->addSql('CREATE TABLE IF NOT EXISTS gift_cards_read (id UUID PRIMARY KEY, balance_amount INT NOT NULL, balance_currency VARCHAR(3) NOT NULL, initial_amount INT NOT NULL, initial_currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, expires_at TIMESTAMPTZ DEFAULT NULL, created_at TIMESTAMPTZ NOT NULL, activated_at TIMESTAMPTZ DEFAULT NULL, suspended_at TIMESTAMPTZ DEFAULT NULL, cancelled_at TIMESTAMPTZ DEFAULT NULL, expired_at TIMESTAMPTZ DEFAULT NULL, depleted_at TIMESTAMPTZ DEFAULT NULL, suspension_duration INT DEFAULT 0, updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gift_cards_read_status ON gift_cards_read (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gift_cards_read_expires_at ON gift_cards_read (expires_at) WHERE expires_at IS NOT NULL');
        $this->addSql("COMMENT ON TABLE gift_cards_read IS 'Read Model projection for Gift Cards - optimized for queries'");
        $this->addSql("COMMENT ON COLUMN gift_cards_read.balance_amount IS 'Current balance in smallest currency unit (grosze)'");
        $this->addSql("COMMENT ON COLUMN gift_cards_read.initial_amount IS 'Original amount when card was created'");
        $this->addSql("COMMENT ON COLUMN gift_cards_read.suspension_duration IS 'Total suspension duration in seconds'");

        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGSERIAL PRIMARY KEY, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL, available_at TIMESTAMP NOT NULL, delivered_at TIMESTAMP DEFAULT NULL)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_queue_name ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_available_at ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_delivered_at ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS gift_cards_read');
        $this->addSql('DROP TABLE IF EXISTS events');
    }
}
