<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Marks eligible past-due invoices as overdue (worker / CLI).
 * Extracted from InvoiceService to keep files ≤500 lines (api-community#64 rework).
 */
class MarkOverdueInvoicesService
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly StatusService $statusService,
    ) {
    }

    /**
     * @return array{updated: int, skipped: int, errors: int}
     */
    public function markOverdueInvoices(?\DateTimeInterface $asOf = null): array
    {
        $asOf = $asOf ?? new \DateTimeImmutable('today');
        $dayStart = \DateTimeImmutable::createFromInterface($asOf)->setTime(0, 0, 0);

        $overdueStatus = $this->statusService->discoveryStatus(
            'overdue',
            'em atraso',
            'invoice'
        );

        $qb = $this->manager->createQueryBuilder();
        $qb->select('i')
            ->from(Invoice::class, 'i')
            ->join('i.status', 's')
            ->where('i.dueDate < :dayStart')
            ->andWhere('LOWER(s.realStatus) NOT IN (:excludedReal)')
            ->andWhere('LOWER(s.status) NOT IN (:excludedStatus)')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('excludedReal', ['closed', 'canceled', 'cancelled', 'overdue', 'paid'])
            ->setParameter('excludedStatus', ['canceled', 'cancelled', 'paid', 'em atraso']);

        $invoices = $qb->getQuery()->getResult();
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($invoices as $invoice) {
            try {
                $current = $invoice->getStatus();
                if ($current instanceof Status) {
                    $rs = strtolower(trim((string) $current->getRealStatus()));
                    $st = strtolower(trim((string) $current->getStatus()));
                    if (in_array($rs, ['closed', 'canceled', 'cancelled', 'overdue', 'paid'], true)
                        || in_array($st, ['canceled', 'cancelled', 'paid', 'em atraso'], true)) {
                        $skipped++;
                        continue;
                    }
                }
                $invoice->setStatus($overdueStatus);
                $this->manager->persist($invoice);
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        if ($updated > 0) {
            $this->manager->flush();
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }
}
