<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116235813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exhibitor (id INT AUTO_INCREMENT NOT NULL, source_inquiry_id INT DEFAULT NULL, assigned_to_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', organization VARCHAR(255) NOT NULL, contact_name VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, website VARCHAR(500) DEFAULT NULL, category VARCHAR(30) NOT NULL, package_tier VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, booth_number VARCHAR(30) DEFAULT NULL, contract_signed_on DATETIME DEFAULT NULL, logo_url VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, year INT NOT NULL, created_on DATETIME DEFAULT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_B2A03D20DB191C55 (source_inquiry_id), INDEX IDX_B2A03D20F4BD7827 (assigned_to_id), INDEX idx_exhibitor_status (status), INDEX idx_exhibitor_tier (package_tier), INDEX idx_exhibitor_category (category), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exhibitor ADD CONSTRAINT FK_B2A03D20DB191C55 FOREIGN KEY (source_inquiry_id) REFERENCES inquiry (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exhibitor ADD CONSTRAINT FK_B2A03D20F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exhibitor DROP FOREIGN KEY FK_B2A03D20DB191C55');
        $this->addSql('ALTER TABLE exhibitor DROP FOREIGN KEY FK_B2A03D20F4BD7827');
        $this->addSql('DROP TABLE exhibitor');
    }
}
