<?php

declare(strict_types=1);

namespace DoctrineMigrations\Financial;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create people_payment junction (people → peoplePayment → paymentType) and backfill from payment_type.people_id';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('payment_type')) {
            return;
        }

        if (!$this->tableExists('people_payment')) {
            $this->addSql('CREATE TABLE people_payment (
                id INT AUTO_INCREMENT NOT NULL,
                people_id INT NOT NULL,
                payment_type_id INT NOT NULL,
                PRIMARY KEY(id),
                UNIQUE KEY people_payment_unique (people_id, payment_type_id),
                CONSTRAINT people_payment_people_fk FOREIGN KEY (people_id) REFERENCES people (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT people_payment_type_fk FOREIGN KEY (payment_type_id) REFERENCES payment_type (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        }

        if ($this->columnExists('payment_type', 'people_id')) {
            $this->addSql('INSERT IGNORE INTO people_payment (people_id, payment_type_id)
                SELECT people_id, id FROM payment_type WHERE people_id IS NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('people_payment')) {
            $this->addSql('DROP TABLE people_payment');
        }
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tableName, $columnName]
        );
    }
}
