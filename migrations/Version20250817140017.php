<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250817140017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Amenity, ResortCategory, and Resort entities with vector embeddings and relationships';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE amenity (id UUID NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, embedding vector(1024) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN amenity.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE resort (id UUID NOT NULL, destination_id UUID NOT NULL, category_id UUID NOT NULL, name VARCHAR(255) NOT NULL, star_rating SMALLINT NOT NULL, total_rooms INT NOT NULL, description TEXT DEFAULT NULL, embedding vector(1024) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D69AD86A816C6140 ON resort (destination_id)');
        $this->addSql('CREATE INDEX IDX_D69AD86A12469DE2 ON resort (category_id)');
        $this->addSql('COMMENT ON COLUMN resort.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN resort.destination_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN resort.category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE resort_amenity (resort_id UUID NOT NULL, amenity_id UUID NOT NULL, PRIMARY KEY(resort_id, amenity_id))');
        $this->addSql('CREATE INDEX IDX_BC67D6937A3ABE5D ON resort_amenity (resort_id)');
        $this->addSql('CREATE INDEX IDX_BC67D6939F9F1305 ON resort_amenity (amenity_id)');
        $this->addSql('COMMENT ON COLUMN resort_amenity.resort_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN resort_amenity.amenity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE resort_category (id UUID NOT NULL, name VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, embedding vector(1024) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN resort_category.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE resort ADD CONSTRAINT FK_D69AD86A816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resort ADD CONSTRAINT FK_D69AD86A12469DE2 FOREIGN KEY (category_id) REFERENCES resort_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resort_amenity ADD CONSTRAINT FK_BC67D6937A3ABE5D FOREIGN KEY (resort_id) REFERENCES resort (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resort_amenity ADD CONSTRAINT FK_BC67D6939F9F1305 FOREIGN KEY (amenity_id) REFERENCES amenity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE resort DROP CONSTRAINT FK_D69AD86A816C6140');
        $this->addSql('ALTER TABLE resort DROP CONSTRAINT FK_D69AD86A12469DE2');
        $this->addSql('ALTER TABLE resort_amenity DROP CONSTRAINT FK_BC67D6937A3ABE5D');
        $this->addSql('ALTER TABLE resort_amenity DROP CONSTRAINT FK_BC67D6939F9F1305');
        $this->addSql('DROP TABLE amenity');
        $this->addSql('DROP TABLE resort');
        $this->addSql('DROP TABLE resort_amenity');
        $this->addSql('DROP TABLE resort_category');
    }
}
