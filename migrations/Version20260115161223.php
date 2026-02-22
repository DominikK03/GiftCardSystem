<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115161223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.deactivated_at IS \'(DC2Type:datetime_immutable)\'');
        // Add new columns as nullable first
        $this->addSql('ALTER TABLE tenants ADD address_city VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD address_country VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD representative_name_first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD representative_name_last_name VARCHAR(100) DEFAULT NULL');

        // Copy data from old columns to new columns
        $this->addSql('UPDATE tenants SET address_city = city WHERE city IS NOT NULL');
        $this->addSql('UPDATE tenants SET address_country = country WHERE country IS NOT NULL');
        $this->addSql('UPDATE tenants SET representative_name_first_name = representative_first_name WHERE representative_first_name IS NOT NULL');
        $this->addSql('UPDATE tenants SET representative_name_last_name = representative_last_name WHERE representative_last_name IS NOT NULL');

        // Make columns NOT NULL
        $this->addSql('ALTER TABLE tenants ALTER COLUMN address_city SET NOT NULL');
        $this->addSql('ALTER TABLE tenants ALTER COLUMN address_country SET NOT NULL');
        $this->addSql('ALTER TABLE tenants ALTER COLUMN representative_name_first_name SET NOT NULL');
        $this->addSql('ALTER TABLE tenants ALTER COLUMN representative_name_last_name SET NOT NULL');

        // Drop old columns
        $this->addSql('ALTER TABLE tenants DROP street');
        $this->addSql('ALTER TABLE tenants DROP country');
        $this->addSql('ALTER TABLE tenants DROP representative_first_name');
        $this->addSql('ALTER TABLE tenants DROP representative_last_name');
        $this->addSql('ALTER TABLE tenants ALTER id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE tenants ALTER id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE tenants ALTER name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants ALTER email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants ALTER nip TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE tenants ALTER nip TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE tenants ALTER phone_number TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE tenants ALTER phone_number TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE tenants ALTER api_key TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE tenants ALTER api_key TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE tenants ALTER api_secret TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants ALTER api_secret TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants RENAME COLUMN city TO address_street');
        $this->addSql('ALTER TABLE tenants RENAME COLUMN postal_code TO address_postal_code');
        $this->addSql('COMMENT ON COLUMN tenants.id IS \'(DC2Type:tenant_id)\'');
        $this->addSql('COMMENT ON COLUMN tenants.name IS \'(DC2Type:tenant_name)\'');
        $this->addSql('COMMENT ON COLUMN tenants.email IS \'(DC2Type:tenant_email)\'');
        $this->addSql('COMMENT ON COLUMN tenants.nip IS \'(DC2Type:nip)\'');
        $this->addSql('COMMENT ON COLUMN tenants.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('COMMENT ON COLUMN tenants.api_key IS \'(DC2Type:api_key)\'');
        $this->addSql('COMMENT ON COLUMN tenants.api_secret IS \'(DC2Type:api_secret)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE users');
        $this->addSql('ALTER TABLE tenants ADD street VARCHAR(500) NOT NULL');
        $this->addSql('ALTER TABLE tenants ADD country VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE tenants ADD representative_first_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE tenants ADD representative_last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE tenants DROP address_city');
        $this->addSql('ALTER TABLE tenants DROP address_country');
        $this->addSql('ALTER TABLE tenants DROP representative_name_first_name');
        $this->addSql('ALTER TABLE tenants DROP representative_name_last_name');
        $this->addSql('ALTER TABLE tenants ALTER id TYPE VARCHAR(36)');
        $this->addSql('ALTER TABLE tenants ALTER name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants ALTER email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE tenants ALTER nip TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE tenants ALTER phone_number TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE tenants ALTER api_key TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE tenants ALTER api_secret TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE tenants RENAME COLUMN address_street TO city');
        $this->addSql('ALTER TABLE tenants RENAME COLUMN address_postal_code TO postal_code');
        $this->addSql('COMMENT ON COLUMN tenants.id IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.name IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.email IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.nip IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.phone_number IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.api_key IS NULL');
        $this->addSql('COMMENT ON COLUMN tenants.api_secret IS NULL');
    }
}
