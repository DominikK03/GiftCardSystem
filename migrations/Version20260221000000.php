<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create my_cards_requests table for customer card lookup verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE my_cards_requests (id VARCHAR(36) NOT NULL, customer_email VARCHAR(255) NOT NULL, verification_code VARCHAR(6) NOT NULL, verified BOOLEAN NOT NULL DEFAULT FALSE, status VARCHAR(30) NOT NULL DEFAULT 'pending_verification', created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("COMMENT ON COLUMN my_cards_requests.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN my_cards_requests.expires_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE my_cards_requests');
    }
}
