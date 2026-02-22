<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add firstName and lastName columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN first_name');
        $this->addSql('ALTER TABLE users DROP COLUMN last_name');
    }
}
