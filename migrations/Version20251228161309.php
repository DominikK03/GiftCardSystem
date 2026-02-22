<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228161309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE IF EXISTS events_id_seq CASCADE');
        $this->addSql('DROP TABLE IF EXISTS events');
        $this->addSql('DROP INDEX idx_gift_cards_read_expires_at');
        $this->addSql('DROP INDEX idx_gift_cards_read_status');
        $this->addSql('ALTER TABLE gift_cards_read ALTER id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE gift_cards_read ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER activated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspended_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER cancelled_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER expired_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER depleted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspension_duration DROP DEFAULT');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspension_duration SET NOT NULL');
        $this->addSql('ALTER TABLE gift_cards_read ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER updated_at DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.balance_amount IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.initial_amount IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.activated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.suspended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.cancelled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.expired_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.depleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.suspension_duration IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_queue_name RENAME TO IDX_75EA56E0FB7336F0');
        $this->addSql('ALTER INDEX idx_available_at RENAME TO IDX_75EA56E0E3BD61CE');
        $this->addSql('ALTER INDEX idx_delivered_at RENAME TO IDX_75EA56E016BA31DB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE events_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE events (id SERIAL NOT NULL, uuid UUID NOT NULL, playhead INT NOT NULL, metadata JSON NOT NULL, payload JSON NOT NULL, recorded_on TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX unique_uuid_playhead ON events (uuid, playhead)');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS NULL');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS NULL');
        $this->addSql('ALTER INDEX idx_75ea56e0e3bd61ce RENAME TO idx_available_at');
        $this->addSql('ALTER INDEX idx_75ea56e016ba31db RENAME TO idx_delivered_at');
        $this->addSql('ALTER INDEX idx_75ea56e0fb7336f0 RENAME TO idx_queue_name');
        $this->addSql('ALTER TABLE gift_cards_read ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE gift_cards_read ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE gift_cards_read ALTER expires_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER activated_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspended_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER cancelled_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER expired_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER depleted_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspension_duration SET DEFAULT 0');
        $this->addSql('ALTER TABLE gift_cards_read ALTER suspension_duration DROP NOT NULL');
        $this->addSql('ALTER TABLE gift_cards_read ALTER updated_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE gift_cards_read ALTER updated_at SET DEFAULT \'now()\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.balance_amount IS \'Current balance in smallest currency unit (grosze)\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.initial_amount IS \'Original amount when card was created\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.expires_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.activated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.suspended_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.cancelled_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.expired_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.depleted_at IS NULL');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.suspension_duration IS \'Total suspension duration in seconds\'');
        $this->addSql('COMMENT ON COLUMN gift_cards_read.updated_at IS NULL');
        $this->addSql('CREATE INDEX idx_gift_cards_read_expires_at ON gift_cards_read (expires_at) WHERE (expires_at IS NOT NULL)');
        $this->addSql('CREATE INDEX idx_gift_cards_read_status ON gift_cards_read (status)');
    }
}
