<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250817062936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pgvector extension for vector similarity search';
    }

    public function up(Schema $schema): void
    {
        // Enable pgvector extension for vector operations
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(Schema $schema): void
    {
        // Disable pgvector extension
        $this->addSql('DROP EXTENSION IF EXISTS vector');
        $this->addSql('CREATE SCHEMA public');
    }
}
