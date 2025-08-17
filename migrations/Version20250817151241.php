<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250817151241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add HNSW indexes for vector columns to optimize similarity searches';
    }

    public function up(Schema $schema): void
    {
        // Add HNSW indexes for vector similarity search optimization
        // HNSW (Hierarchical Navigable Small World) is optimal for high-dimensional vector similarity queries
        // Note: CONCURRENT indexes will need to be created separately outside transactions
        
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_amenity_embedding_hnsw ON amenity USING hnsw (embedding vector_cosine_ops)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_resort_category_embedding_hnsw ON resort_category USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        // Remove HNSW indexes
        $this->addSql('DROP INDEX IF EXISTS idx_amenity_embedding_hnsw');
        $this->addSql('DROP INDEX IF EXISTS idx_resort_category_embedding_hnsw');
    }
}
