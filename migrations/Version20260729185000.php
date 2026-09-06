<?php

declare(strict_types=1);

namespace DoctrineMigrations\Financial;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes for invoice financial list filters';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('invoice')) {
            return;
        }

        if (!$this->indexExists('invoice', 'invoice_receiver_type_due_id_idx')) {
            $this->addSql('CREATE INDEX invoice_receiver_type_due_id_idx ON invoice (receiver_id, invoice_type, due_date, id)');
        }

        if (!$this->indexExists('invoice', 'invoice_payer_type_due_id_idx')) {
            $this->addSql('CREATE INDEX invoice_payer_type_due_id_idx ON invoice (payer_id, invoice_type, due_date, id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('invoice')) {
            if ($this->indexExists('invoice', 'invoice_receiver_type_due_id_idx')) {
                $this->addSql('DROP INDEX invoice_receiver_type_due_id_idx ON invoice');
            }
            if ($this->indexExists('invoice', 'invoice_payer_type_due_id_idx')) {
                $this->addSql('DROP INDEX invoice_payer_type_due_id_idx ON invoice');
            }
        }
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
    }
}
