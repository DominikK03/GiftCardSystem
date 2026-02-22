<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activation_requests and card_assignments tables, add card_number and pin to gift_cards_read';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activation_requests (id VARCHAR(36) NOT NULL, card_number VARCHAR(12) NOT NULL, customer_email VARCHAR(255) NOT NULL, verification_code VARCHAR(6) NOT NULL, verified BOOLEAN NOT NULL DEFAULT FALSE, gift_card_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, return_url VARCHAR(2048) NOT NULL, callback_url VARCHAR(2048) DEFAULT NULL, status VARCHAR(30) NOT NULL DEFAULT \'pending_verification\', created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_activation_card_number ON activation_requests (card_number)');
        $this->addSql('CREATE INDEX idx_activation_status ON activation_requests (status)');
        $this->addSql('COMMENT ON COLUMN activation_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN activation_requests.expires_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE card_assignments (id VARCHAR(36) NOT NULL, gift_card_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, customer_email VARCHAR(255) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uq_card_assignment_gift_card ON card_assignments (gift_card_id)');
        $this->addSql('COMMENT ON COLUMN card_assignments.assigned_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE gift_cards_read ADD card_number VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE gift_cards_read ADD pin VARCHAR(8) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX idx_gift_cards_read_card_number ON gift_cards_read (card_number)');

        $this->addSql('ALTER TABLE tenants ADD allowed_redirect_domain VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activation_requests');
        $this->addSql('DROP TABLE card_assignments');

        $this->addSql('DROP INDEX idx_gift_cards_read_card_number');
        $this->addSql('ALTER TABLE gift_cards_read DROP COLUMN card_number');
        $this->addSql('ALTER TABLE gift_cards_read DROP COLUMN pin');

        $this->addSql('ALTER TABLE tenants DROP COLUMN allowed_redirect_domain');
    }
}
