<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename first_name/last_name to profile_first_name/profile_last_name in users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users RENAME COLUMN first_name TO profile_first_name');
        $this->addSql('ALTER TABLE users RENAME COLUMN last_name TO profile_last_name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users RENAME COLUMN profile_first_name TO first_name');
        $this->addSql('ALTER TABLE users RENAME COLUMN profile_last_name TO last_name');
    }
}
