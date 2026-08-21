<?php

namespace ControleOnline\Command;

use ControleOnline\Service\InvoiceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:invoice:notify-overdue',
    description: 'Envia cobrança por e-mail e WhatsApp para faturas em atraso/abertas vencidas ainda não notificadas (anti-spam via invoice.notified).',
)]
class NotifyOverdueChargesCommand extends Command
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

        $result = $this->invoiceService->notifyOverdueCharges($asOf);

        $output->writeln(sprintf(
            '[app:invoice:notify-overdue] groups=%d email_ok=%d email_fail=%d whatsapp_ok=%d whatsapp_fail=%d invoices_marked=%d skipped=%d',
            $result['groups'],
            $result['email_ok'],
            $result['email_fail'],
            $result['whatsapp_ok'],
            $result['whatsapp_fail'],
            $result['invoices_marked'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
