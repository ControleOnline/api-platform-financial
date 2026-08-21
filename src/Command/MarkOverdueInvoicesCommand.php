<?php

namespace ControleOnline\Command;

use ControleOnline\Service\InvoiceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:invoice:mark-overdue',
    description: 'Marca faturas não pagas/não canceladas com dueDate anterior a hoje como em atraso (overdue).',
)]
class MarkOverdueInvoicesCommand extends Command
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'as-of',
            null,
            InputOption::VALUE_REQUIRED,
            'Data de referência (Y-m-d). Default: hoje.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asOfRaw = $input->getOption('as-of');
        $asOf = null;
        if (is_string($asOfRaw) && trim($asOfRaw) !== '') {
            try {
                $asOf = new \DateTimeImmutable(trim($asOfRaw));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Data inválida: %s</error>', $asOfRaw));
                return Command::FAILURE;
            }
        }

        $result = $this->invoiceService->markOverdueInvoices($asOf);

        $output->writeln(sprintf(
            '[app:invoice:mark-overdue] updated=%d skipped=%d errors=%d',
            $result['updated'],
            $result['skipped'],
            $result['errors'],
        ));

        return ($result['errors'] ?? 0) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
