<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261111234140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inquiry (id INT AUTO_INCREMENT NOT NULL, assigned_to_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, organization VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, type VARCHAR(30) NOT NULL, message LONGTEXT NOT NULL, consent TINYINT(1) NOT NULL, source_path VARCHAR(255) DEFAULT NULL, status VARCHAR(30) NOT NULL, created_on DATETIME DEFAULT NULL, updated_on DATETIME DEFAULT NULL, INDEX IDX_5A3903F0F4BD7827 (assigned_to_id), INDEX idx_inquiry_status (status), INDEX idx_inquiry_type (type), INDEX idx_inquiry_created_on (created_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inquiry ADD CONSTRAINT FK_5A3903F0F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inquiry DROP FOREIGN KEY FK_5A3903F0F4BD7827');
        $this->addSql('DROP TABLE inquiry');
    }
}
