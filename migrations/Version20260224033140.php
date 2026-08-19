<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224033140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE landingpage ADD hero_header VARCHAR(255) DEFAULT NULL, ADD hero_text VARCHAR(255) DEFAULT NULL, DROP hearo_header, DROP hearo_text');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE landingpage ADD hearo_header VARCHAR(255) DEFAULT NULL, ADD hearo_text VARCHAR(255) DEFAULT NULL, DROP hero_header, DROP hero_text');
    }
}
