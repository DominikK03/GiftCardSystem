<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208145003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_documents table for storing document metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenant_documents (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, type VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, size INT NOT NULL, storage_path VARCHAR(500) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_document_tenant_id ON tenant_documents (tenant_id)');
        $this->addSql('CREATE INDEX idx_document_type ON tenant_documents (type)');
        $this->addSql('COMMENT ON COLUMN tenant_documents.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_documents');
    }
}
