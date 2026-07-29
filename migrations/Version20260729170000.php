<?php

declare(strict_types=1);

namespace DoctrineMigrations\Financial;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes for invoice financial list filters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX invoice_receiver_type_due_id_idx ON invoice (receiver_id, invoice_type, due_date, id)');
        $this->addSql('CREATE INDEX invoice_payer_type_due_id_idx ON invoice (payer_id, invoice_type, due_date, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX invoice_receiver_type_due_id_idx ON invoice');
        $this->addSql('DROP INDEX invoice_payer_type_due_id_idx ON invoice');
    }
}
