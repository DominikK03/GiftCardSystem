<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202173945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_webhooks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenant_webhooks (id SERIAL NOT NULL, tenant_id VARCHAR(36) NOT NULL, url VARCHAR(500) NOT NULL, events JSON NOT NULL, is_active BOOLEAN NOT NULL, secret VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_webhook_tenant_id ON tenant_webhooks (tenant_id)');
        $this->addSql('COMMENT ON COLUMN tenant_webhooks.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_webhooks');
    }
}
