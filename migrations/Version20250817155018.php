<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250817155018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vector embedding field to destination table and create HNSW index';
    }

    public function up(Schema $schema): void
    {
        // Add embedding column to destination table
        $this->addSql('ALTER TABLE destination ADD COLUMN embedding vector(1024)');
        
        // Add HNSW index for destination embeddings
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_destination_embedding_hnsw ON destination USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        // Remove HNSW index
        $this->addSql('DROP INDEX IF EXISTS idx_destination_embedding_hnsw');
        
        // Remove embedding column
        $this->addSql('ALTER TABLE destination DROP COLUMN IF EXISTS embedding');
    }
}
