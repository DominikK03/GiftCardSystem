<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228213512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE IF EXISTS events_id_seq CASCADE');
        $this->addSql('CREATE TABLE tenants (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, nip VARCHAR(10) NOT NULL, street VARCHAR(500) NOT NULL, city VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, country VARCHAR(100) NOT NULL, phone_number VARCHAR(20) NOT NULL, representative_first_name VARCHAR(100) NOT NULL, representative_last_name VARCHAR(100) NOT NULL, api_key VARCHAR(32) NOT NULL, api_secret VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, suspended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8FC96BBE7927C74 ON tenants (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8FC96BBC912ED9D ON tenants (api_key)');
        $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.suspended_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.cancelled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP TABLE IF EXISTS events');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE events_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE events (id SERIAL NOT NULL, uuid UUID NOT NULL, playhead INT NOT NULL, metadata JSON NOT NULL, payload JSON NOT NULL, recorded_on TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX unique_uuid_playhead ON events (uuid, playhead)');
        $this->addSql('DROP TABLE tenants');
    }
}
