<?php

declare(strict_types=1);

namespace DoctrineMigrations\Financial;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline schema for financial module from s.controleonline.com";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('CREATE TABLE IF NOT EXISTS `card` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `people_id` int(11) NOT NULL,
  `type` enum(\'credit\',\'debit\',\'voucher\',\'\') CHARACTER SET utf8 NOT NULL,
  `name` blob NOT NULL,
  `document` blob NOT NULL,
  `number_group_1` blob NOT NULL,
  `number_group_2` blob NOT NULL,
  `number_group_3` blob NOT NULL,
  `number_group_4` blob NOT NULL,
  `ccv` blob NOT NULL COMMENT \'remover. proibido pelas normas internacionais de segurança (PCI-DSS)\',
  `expiration_month` blob NOT NULL,
  `expiration_year` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `people_id` (`people_id`),
  CONSTRAINT `card_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payer_id` int(11) DEFAULT NULL,
  `portion_number` int(11) NOT NULL,
  `installments` int(11) NOT NULL,
  `installment_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `invoice_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `due_date` date NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT \'0\',
  `category_id` int(11) DEFAULT NULL,
  `invoice_bank_id` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
  `other_informations` longtext CHARACTER SET utf8 NOT NULL,
  `source_wallet_id` int(11) DEFAULT NULL,
  `destination_wallet_id` int(11) DEFAULT NULL,
  `payment_type_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'invoice\',
  PRIMARY KEY (`id`),
  KEY `invoice_status_id` (`status_id`),
  KEY `invoice_ibfk_2` (`category_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `wallet_id` (`destination_wallet_id`),
  KEY `payment_type_id` (`payment_type_id`),
  KEY `installment_id` (`installment_id`),
  KEY `source_wallet_id` (`source_wallet_id`),
  KEY `user_id` (`user_id`),
  KEY `payer_id` (`payer_id`),
  KEY `invoice_ibfk_11` (`device_id`),
  CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `status` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_10` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`),
  CONSTRAINT `invoice_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_4` FOREIGN KEY (`payer_id`) REFERENCES `people` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_5` FOREIGN KEY (`destination_wallet_id`) REFERENCES `wallet` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_6` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_type` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_7` FOREIGN KEY (`installment_id`) REFERENCES `invoice` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_8` FOREIGN KEY (`source_wallet_id`) REFERENCES `wallet` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `invoice_ibfk_9` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32886 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `payment_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `people_id` int(11) NOT NULL,
  `payment_type` varchar(50) CHARACTER SET utf8 NOT NULL,
  `frequency` enum(\'monthly\',\'daily\',\'weekly\',\'single\') CHARACTER SET utf8 NOT NULL,
  `installments` enum(\'single\',\'split\') CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `people_id` (`people_id`,`payment_type`),
  CONSTRAINT `payment_type_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `wallet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `people_id` int(11) NOT NULL,
  `wallet` varchar(50) CHARACTER SET utf8 NOT NULL,
  `balance` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `people_id` (`people_id`,`wallet`),
  CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `wallet_payment_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` int(11) NOT NULL,
  `payment_type_id` int(11) NOT NULL,
  `payment_code` varchar(80) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_id` (`wallet_id`,`payment_type_id`),
  KEY `payment_type_id` (`payment_type_id`),
  CONSTRAINT `wallet_payment_type_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `wallet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `wallet_payment_type_ibfk_2` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=422 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE OR REPLACE VIEW `vw_invoice_monthly_report` AS select month(`invoice`.`due_date`) AS `month`,year(`invoice`.`due_date`) AS `year`,`invoice`.`payer_id` AS `payer_id`,`invoice`.`receiver_id` AS `receiver_id`,`invoice`.`category_id` AS `category_id`,`C`.`name` AS `category_name`,coalesce(`C`.`parent_id`,`invoice`.`category_id`) AS `parent_id`,coalesce(`PC`.`name`,`C`.`name`) AS `parent_category_name`,coalesce(sum(`invoice`.`price`),0) AS `total_price` from ((`invoice` left join `category` `C` on((`C`.`id` = `invoice`.`category_id`))) left join `category` `PC` on((`PC`.`id` = `C`.`parent_id`))) where (1 = 1) group by `invoice`.`receiver_id`,`invoice`.`payer_id`,month(`invoice`.`due_date`),year(`invoice`.`due_date`),`invoice`.`category_id`,`C`.`parent_id`');
        $this->addSql('CREATE OR REPLACE VIEW `vw_orders_by_hour` AS select `daily_hourly_orders`.`provider_id` AS `provider_id`,`daily_hourly_orders`.`order_hour` AS `order_hour`,avg(`daily_hourly_orders`.`order_count`) AS `average_orders` from (select `orders`.`provider_id` AS `provider_id`,hour(`orders`.`alter_date`) AS `order_hour`,count(0) AS `order_count` from `orders` group by `orders`.`provider_id`,hour(`orders`.`alter_date`),cast(`orders`.`alter_date` as date)) `daily_hourly_orders` group by `daily_hourly_orders`.`provider_id`,`daily_hourly_orders`.`order_hour` order by `daily_hourly_orders`.`provider_id`,`daily_hourly_orders`.`order_hour`');
        $this->addSql('CREATE OR REPLACE VIEW `vw_products_by_day` AS select `o`.`provider_id` AS `provider_id`,`o`.`app` AS `app`,cast(`o`.`alter_date` as date) AS `date`,extract(hour from `o`.`alter_date`) AS `hour`,sum(`op`.`quantity`) AS `quantity`,`op`.`total` AS `total` from (`orders` `o` join `order_product` `op` on((`o`.`id` = `op`.`order_id`))) group by `o`.`provider_id`,`o`.`app`,cast(`o`.`alter_date` as date),extract(hour from `o`.`alter_date`) order by cast(`o`.`alter_date` as date)');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        return;
    }
}
