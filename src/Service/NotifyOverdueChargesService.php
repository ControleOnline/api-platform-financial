<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Document;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Notifies overdue/open past-due invoices via e-mail and WhatsApp (worker / CLI).
 * Extracted from InvoiceService for file-size limit (api-community#64 QA rework).
 */
class NotifyOverdueChargesService
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
    ) {
    }

    public function notifyOverdueCharges(?\DateTimeInterface $asOf = null): array
    {
        $asOf = $asOf ?? new \DateTimeImmutable('today');
        $dayStart = \DateTimeImmutable::createFromInterface($asOf)->setTime(0, 0, 0);

        $qb = $this->manager->createQueryBuilder();
        $qb->select('i')
            ->from(Invoice::class, 'i')
            ->join('i.status', 's')
            ->where('i.dueDate < :dayStart')
            ->andWhere('i.notified = :notified')
            ->andWhere('LOWER(s.realStatus) NOT IN (:excludedReal)')
            ->andWhere('LOWER(s.status) NOT IN (:excludedStatus)')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('notified', false)
            ->setParameter('excludedReal', ['closed', 'canceled', 'cancelled', 'paid'])
            ->setParameter('excludedStatus', ['canceled', 'cancelled', 'paid']);

        /** @var Invoice[] $invoices */
        $invoices = $qb->getQuery()->getResult();

        $result = [
            'groups' => 0,
            'email_ok' => 0,
            'email_fail' => 0,
            'whatsapp_ok' => 0,
            'whatsapp_fail' => 0,
            'invoices_marked' => 0,
            'skipped' => 0,
        ];

        if ($invoices === []) {
            return $result;
        }

        // Group by payer people id
        $byPayer = [];
        foreach ($invoices as $invoice) {
            $payer = $invoice->getPayer();
            if (!$payer instanceof People) {
                $result['skipped']++;
                continue;
            }
            $pid = (int) $payer->getId();
            if (!isset($byPayer[$pid])) {
                $byPayer[$pid] = ['people' => $payer, 'invoices' => [], 'receiver' => $invoice->getReceiver()];
            }
            $byPayer[$pid]['invoices'][] = $invoice;
        }

        $publicBase = rtrim((string) (
            $_ENV['MANAGER_PUBLIC_URL']
            ?? $_SERVER['MANAGER_PUBLIC_URL']
            ?? getenv('MANAGER_PUBLIC_URL')
            ?? $_ENV['APP_PUBLIC_URL']
            ?? ''
        ), '/');
        if ($publicBase === '') {
            $publicBase = 'https://manager.controleonline.com';
        }

        foreach ($byPayer as $group) {
            $result['groups']++;
            /** @var People $people */
            $people = $group['people'];
            /** @var Invoice[] $groupInvoices */
            $groupInvoices = $group['invoices'];
            $receiver = $group['receiver'];
            $receiverId = $receiver instanceof People ? (int) $receiver->getId() : 0;

            $document = $this->resolvePrimaryDocument($people);
            if ($document === null || $document === '') {
                $result['skipped'] += count($groupInvoices);
                continue;
            }

            $total = 0.0;
            foreach ($groupInvoices as $inv) {
                $total += (float) $inv->getPrice();
            }
            $link = sprintf(
                '%s/paylist?document=%s&company=%d',
                $publicBase,
                rawurlencode($document),
                $receiverId
            );

            $creditorName = $receiver instanceof People
                ? (string) ($receiver->getName() ?: $receiver->getAlias() ?: 'Credor')
                : 'Credor';
            $debtorName = (string) ($people->getName() ?: $people->getAlias() ?: 'Cliente');
            $subject = sprintf('Cobrança — pendências em aberto (%s)', $creditorName);
            $bodyHtml = $this->buildCollectionEmailHtml(
                $debtorName,
                $creditorName,
                $total,
                count($groupInvoices),
                $link
            );
            $waText = $this->buildCollectionWhatsAppText(
                $debtorName,
                $creditorName,
                $total,
                count($groupInvoices),
                $link
            );

            $email = $this->resolvePrimaryEmail($people);
            if ($email !== null) {
                try {
                    $this->sendCollectionEmail($email, $subject, $bodyHtml);
                    $result['email_ok']++;
                } catch (\Throwable $e) {
                    $result['email_fail']++;
                    error_log(sprintf('[notifyOverdueCharges] email fail people=%d: %s', $people->getId(), $e->getMessage()));
                }
            } else {
                $result['email_fail']++;
            }

            $phoneE164 = $this->resolvePrimaryPhoneE164($people);
            if ($phoneE164 !== null) {
                try {
                    $this->sendCollectionWhatsApp($phoneE164, $waText);
                    $result['whatsapp_ok']++;
                } catch (\Throwable $e) {
                    $result['whatsapp_fail']++;
                    error_log(sprintf('[notifyOverdueCharges] whatsapp fail people=%d: %s', $people->getId(), $e->getMessage()));
                }
            } else {
                $result['whatsapp_fail']++;
            }

            // Mark notified even if one channel failed (anti-spam / once per cycle)
            foreach ($groupInvoices as $inv) {
                $inv->setNotified(true);
                $this->manager->persist($inv);
                $result['invoices_marked']++;
            }
        }

        if ($result['invoices_marked'] > 0) {
            $this->manager->flush();
        }

        return $result;
    }

    private function resolvePrimaryDocument(People $people): ?string
    {
        $docs = $people->getDocument();
        if ($docs === null) {
            return null;
        }
        foreach ($docs as $doc) {
            if ($doc instanceof Document) {
                $value = trim((string) $doc->getDocument());
                if ($value !== '' && $value !== '0') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function resolvePrimaryEmail(People $people): ?string
    {
        $emails = $people->getEmail();
        if ($emails === null) {
            return null;
        }
        foreach ($emails as $emailEntity) {
            if (!is_object($emailEntity) || !method_exists($emailEntity, 'getEmail')) {
                continue;
            }
            $addr = strtolower(trim((string) $emailEntity->getEmail()));
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                return $addr;
            }
        }
        return null;
    }

    private function resolvePrimaryPhoneE164(People $people): ?string
    {
        $phones = $people->getPhone();
        if ($phones === null) {
            return null;
        }
        foreach ($phones as $phoneEntity) {
            if (!is_object($phoneEntity)) {
                continue;
            }
            $ddi = method_exists($phoneEntity, 'getDdi') ? (int) $phoneEntity->getDdi() : 55;
            $ddd = method_exists($phoneEntity, 'getDdd') ? (int) $phoneEntity->getDdd() : 0;
            $num = method_exists($phoneEntity, 'getPhone') ? (int) $phoneEntity->getPhone() : 0;
            if ($num <= 0) {
                continue;
            }
            if ($ddi <= 0) {
                $ddi = 55;
            }
            $local = $ddd > 0 ? sprintf('%d%d', $ddd, $num) : (string) $num;
            return sprintf('%d%s', $ddi, $local);
        }
        return null;
    }

    private function buildCollectionEmailHtml(
        string $debtorName,
        string $creditorName,
        float $total,
        int $count,
        string $link
    ): string {
        $totalFmt = number_format($total, 2, ',', '.');
        return sprintf(
            '<p>Olá %s,</p>'
            . '<p>Identificamos <strong>%d</strong> pendência(s) em aberto com <strong>%s</strong>, '
            . 'no valor total de <strong>R$ %s</strong>.</p>'
            . '<p><a href="%s">Clique aqui para ver o detalhe e regularizar</a>.</p>'
            . '<p>Este é um aviso automático de cobrança. Em caso de dúvida, responda a este e-mail.</p>',
            htmlspecialchars($debtorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $count,
            htmlspecialchars($creditorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $totalFmt,
            htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    private function buildCollectionWhatsAppText(
        string $debtorName,
        string $creditorName,
        float $total,
        int $count,
        string $link
    ): string {
        $totalFmt = number_format($total, 2, ',', '.');
        return sprintf(
            "Olá %s,\n\nIdentificamos %d pendência(s) em aberto com %s, total R$ %s.\n\nAcesse para regularizar:\n%s\n\nAviso automático de cobrança.",
            $debtorName,
            $count,
            $creditorName,
            $totalFmt,
            $link
        );
    }

    private function sendCollectionEmail(string $to, string $subject, string $bodyHtml): void
    {
        // Prefer Symfony Mailer DSN from env (same pattern as api-community EmailService)
        $dsn = trim((string) (
            $_ENV['MAILER_URL'] ?? $_ENV['MAILER_DSN']
            ?? $_SERVER['MAILER_URL'] ?? $_SERVER['MAILER_DSN']
            ?? getenv('MAILER_URL') ?: getenv('MAILER_DSN') ?: ''
        ));
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            // Capture-only mode for tests / local
            $captureDir = trim((string) ($_ENV['EMAIL_CAPTURE_DIR'] ?? getenv('EMAIL_CAPTURE_DIR') ?: ''));
            if ($captureDir !== '') {
                if (!is_dir($captureDir)) {
                    @mkdir($captureDir, 0777, true);
                }
                @file_put_contents(
                    rtrim($captureDir, '/') . '/collection-' . date('YmdHis') . '.json',
                    json_encode(['to' => $to, 'subject' => $subject, 'html' => $bodyHtml], JSON_PRETTY_PRINT)
                );
                return;
            }
            throw new \RuntimeException('MAILER_URL/MAILER_DSN not configured');
        }

        if (!class_exists(\Symfony\Component\Mailer\Transport::class)) {
            throw new \RuntimeException('Symfony Mailer not available in this runtime');
        }

        $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
        $email = (new \Symfony\Component\Mime\Email())
            ->to($to)
            ->subject($subject)
            ->html($bodyHtml);

        $from = trim((string) ($_ENV['MAILER_FROM'] ?? getenv('MAILER_FROM') ?: 'no-reply@controleonline.com'));
        $email->from($from);
        $transport->send($email);
    }

    private function sendCollectionWhatsApp(string $phoneE164, string $text): void
    {
        $base = rtrim((string) (
            $_ENV['WHATSAPP_SERVER']
            ?? $_SERVER['WHATSAPP_SERVER']
            ?? getenv('WHATSAPP_SERVER')
            ?? ''
        ), '/');
        if ($base === '') {
            throw new \RuntimeException('WHATSAPP_SERVER not configured');
        }

        $sessionPhone = trim((string) (
            $_ENV['WHATSAPP_SESSION_PHONE']
            ?? $_SERVER['WHATSAPP_SESSION_PHONE']
            ?? getenv('WHATSAPP_SESSION_PHONE')
            ?? ''
        ));
        if ($sessionPhone === '') {
            // fallback: use same number or require env
            throw new \RuntimeException('WHATSAPP_SESSION_PHONE not configured (session that sends)');
        }

        $apiKey = trim((string) (
            $_ENV['WHATSAPP_API_KEY']
            ?? $_SERVER['WHATSAPP_API_KEY']
            ?? getenv('WHATSAPP_API_KEY')
            ?? getenv('API_KEY')
            ?? ''
        ));

        $url = sprintf('%s/messages/%s', $base, rawurlencode($sessionPhone));
        $payload = json_encode([
            'number' => $phoneE164,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'x-api-key: ' . $apiKey;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            throw new \RuntimeException('WhatsApp HTTP request failed');
        }
        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(2\d\d)\s/', $statusLine)) {
            throw new \RuntimeException('WhatsApp HTTP non-2xx: ' . $statusLine . ' body=' . substr((string) $response, 0, 200));
        }
    }


}
