<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317004438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advanced_order (id INT AUTO_INCREMENT NOT NULL, order_id INT DEFAULT NULL, scheduled_for DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', notes VARCHAR(1000) DEFAULT NULL, advance_notice_hours INT DEFAULT NULL, advance_notice_days INT DEFAULT NULL, UNIQUE INDEX UNIQ_D7EF5B908D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE advanced_order ADD CONSTRAINT FK_D7EF5B908D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE dish ADD sort_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE landingpage ADD hero_header VARCHAR(255) DEFAULT NULL, ADD hero_text VARCHAR(255) DEFAULT NULL, DROP hearo_header, DROP hearo_text');
        $this->addSql('ALTER TABLE `order` ADD applied_first_order_discount_id INT DEFAULT NULL, ADD tax_amount NUMERIC(10, 2) DEFAULT NULL, ADD is_advanced_order TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398299B71E4 FOREIGN KEY (applied_first_order_discount_id) REFERENCES first_order (id)');
        $this->addSql('CREATE INDEX IDX_F5299398299B71E4 ON `order` (applied_first_order_discount_id)');
        $this->addSql('ALTER TABLE order_item ADD variant_name VARCHAR(100) DEFAULT NULL, ADD accrued VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD local_account TINYINT(1) DEFAULT NULL, ADD google_id VARCHAR(255) DEFAULT NULL, ADD avatar VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advanced_order DROP FOREIGN KEY FK_D7EF5B908D9F6D38');
        $this->addSql('DROP TABLE advanced_order');
        $this->addSql('ALTER TABLE dish DROP sort_id');
        $this->addSql('ALTER TABLE landingpage ADD hearo_header VARCHAR(255) DEFAULT NULL, ADD hearo_text VARCHAR(255) DEFAULT NULL, DROP hero_header, DROP hero_text');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398299B71E4');
        $this->addSql('DROP INDEX IDX_F5299398299B71E4 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP applied_first_order_discount_id, DROP tax_amount, DROP is_advanced_order');
        $this->addSql('ALTER TABLE order_item DROP variant_name, DROP accrued');
        $this->addSql('ALTER TABLE `user` DROP local_account, DROP google_id, DROP avatar');
    }
}
