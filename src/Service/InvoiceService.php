<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use ControleOnline\Entity\PaymentType;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
as Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Wallet;
use ControleOnline\Service\OrderService;
use ControleOnline\Service\OrderProductQueueService;
use DateTime;
use Exception;

use function PHPUnit\Framework\throwException;

class InvoiceService
{
    private $request;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $peopleService,
        private RequestStack $requestStack,
        private BraspagService $braspagService,
        private StatusService $statusService,
        private OrderPrintService $orderPrintService,
        private OrderService $orderService,
        private OrderProductQueueService $orderProductQueueService,
        private mixed $periodicReceivableDispatcher = null,

    ) {
        $this->request = $this->requestStack->getCurrentRequest();
    }

    /**
     * Terminal / paid invoice realStatus values that must not regress to waiting payment.
     * Legitimate exits (cancel, chargeback, refund) use distinct realStatus values.
     */
    private const PAID_INVOICE_REAL_STATUSES = ['paid', 'closed'];

    private const WAITING_PAYMENT_LIKE_REAL_STATUSES = [
        'waiting payment',
        'waiting retrieve',
        'pending',
    ];

    public function setPayed(Invoice $invoice)
    {
        $status = $this->statusService->discoveryStatus(
            'closed',
            'paid',
            'invoice'
        );
        $this->applyInvoiceStatus($invoice, $status);
        $this->manager->persist($invoice);
        $this->manager->flush();
        return $invoice;
    }

    /**
     * Apply invoice status with guard against regressing a paid/closed invoice
     * back to waiting-payment-like states (stale callbacks, out-of-order events).
     *
     * Cancel / refund / chargeback transitions remain allowed because they use
     * distinct realStatus values outside WAITING_PAYMENT_LIKE_REAL_STATUSES.
     */
    public function applyInvoiceStatus(Invoice $invoice, Status $newStatus): void
    {
        $current = $invoice->getStatus();
        $currentReal = $this->normalizeStatusValue($current?->getRealStatus());
        $newReal = $this->normalizeStatusValue($newStatus->getRealStatus());

        if (
            $currentReal !== null
            && in_array($currentReal, self::PAID_INVOICE_REAL_STATUSES, true)
            && in_array($newReal, self::WAITING_PAYMENT_LIKE_REAL_STATUSES, true)
        ) {
            // Ignore obsolete update; keep terminal paid/closed state.
            return;
        }

        $invoice->setStatus($newStatus);
    }

    public function isInvoicePaidOrClosed(Invoice $invoice): bool
    {
        $real = $this->normalizeStatusValue($invoice->getStatus()?->getRealStatus());
        return $real !== null && in_array($real, self::PAID_INVOICE_REAL_STATUSES, true);
    }

    private function normalizeStatusValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strtolower(trim($value));
    }

    public function syncItauPaymentStatus(Invoice $invoice, bool $promisePaid, bool $paid): void
    {
        // Paid/closed invoice must not be moved back by delayed promise/paid flags.
        if ($this->isInvoicePaidOrClosed($invoice) && !$paid) {
            return;
        }

        if ($promisePaid && !$this->isInvoicePaidOrClosed($invoice)) {
            $status = $this->statusService->discoveryStatus(
                'open',
                'waiting retrieve',
                'invoice'
            );

            $hasOrderUpdates = false;
            foreach ($invoice->getOrder() as $orders) {
                $order = $orders->getOrder();
                if ($order->getStatus()->getStatus() !== 'waiting payment') {
                    continue;
                }

                $order->setStatus($status);
                $order->setNotified(0);
                $this->manager->persist($order);
                $hasOrderUpdates = true;
            }

            if ($hasOrderUpdates) {
                $this->manager->flush();
            }
        }

        if ($paid) {
            $this->setPayed($invoice);
        }
    }

    /**
     * @return Invoice[]
     */
    public function cancelSingleOrderInvoicesForCanceledOrder(Order $order, Status $canceledStatus): array
    {
        if (!$this->isCanceledStatus($order->getStatus())) {
            return [];
        }

        $canceledInvoices = [];
        $targetOrderId = (int) ($order->getId() ?? 0);

        foreach ($order->getInvoice() as $orderInvoice) {
            if (!$orderInvoice instanceof OrderInvoice) {
                continue;
            }

            $invoice = $orderInvoice->getInvoice();
            if (!$invoice instanceof Invoice) {
                continue;
            }

            $invoiceOrders = $invoice->getOrder();
            if (!method_exists($invoiceOrders, 'count') || $invoiceOrders->count() !== 1) {
                continue;
            }

            $singleLink = method_exists($invoiceOrders, 'first')
                ? $invoiceOrders->first()
                : null;

            if (!$singleLink instanceof OrderInvoice) {
                continue;
            }

            $linkedOrder = $singleLink->getOrder();
            if (!$linkedOrder instanceof Order) {
                continue;
            }

            if ($targetOrderId > 0 && (int) ($linkedOrder->getId() ?? 0) !== $targetOrderId) {
                continue;
            }

            if ($this->isCanceledStatus($invoice->getStatus())) {
                continue;
            }

            $invoice->setStatus($canceledStatus);
            $canceledInvoices[] = $invoice;
        }

        return $canceledInvoices;
    }

    public function createInvoice(
        ?Order $order = null,
        ?People $payer = null,
        People $receiver,
        $price,
        Status $status,
        DateTime $dueDate,
        ?Wallet $source_wallet = null,
        ?Wallet $destination_wallet = null,
        $portion = 1,
        $installments = 1,
        $installment_id =  null,
        ?string $description = null
    ): Invoice {

        $paymentType = $this->manager->getRepository(PaymentType::class)->find(1);

        $invoice = new Invoice();
        $invoice->setPayer($payer);
        $invoice->setReceiver($receiver);
        $invoice->setPrice($price);
        $invoice->setDueDate($dueDate);
        $invoice->setSourceWallet($source_wallet);
        $invoice->setDestinationWallet($destination_wallet);
        $invoice->setPortion($portion);
        $invoice->setInstallments($installments);
        $invoice->setInstallmentId($installment_id);
        $invoice->setStatus($status);
        $invoice->setPaymentType($paymentType);
        $invoice->setDescription($description);
        $this->manager->persist($invoice);
        if ($order)
            $this->createOrderInvoice($order, $invoice, $price);
        $this->manager->flush();
        return $invoice;
    }

    public function createInvoiceByOrder(Order $order, $price, ?Status $status = null, DateTime $dueDate, ?Wallet $source_wallet = null, ?Wallet $destination_wallet = null, $portion = 1, $installments = 1, $installment_id =  null, ?string $description = null): Invoice
    {
        $financialOrder = $this->orderService->resolveFinancialOrder($order);

        if (!$source_wallet && !$destination_wallet)
            throw new Exception("Need a source or destination Wallet", 301);
        $status = $this->statusService->discoveryStatus(
            'pending',
            'waiting payment',
            'invoice'
        );
        return $this->createInvoice(
            $financialOrder,
            $financialOrder->getPayer() ?: $financialOrder->getClient(),
            $financialOrder->getProvider(),
            $price,
            $status,
            $dueDate,
            $source_wallet,
            $destination_wallet,
            $portion,
            $installments,
            $installment_id,
            $description
        );
    }

    protected function createOrderInvoice(Order $order, Invoice $invoice, $price = 0): OrderInvoice
    {

        $orderInvoice = $this->manager->getRepository(OrderInvoice::class)->findOneBy([
            'invoice' => $invoice,
            'order' =>  $order
        ]);

        if (!$orderInvoice)
            $orderInvoice = new OrderInvoice();
        $orderInvoice->setOrder($order);
        $orderInvoice->setInvoice($invoice);
        $orderInvoice->setRealPrice($price);

        $this->manager->persist($orderInvoice);
        $this->manager->flush();
        $this->payOrder($order);
        return $orderInvoice;
    }

    public function postPersist(Invoice $invoice)
    {
        if (!$this->request) return;
        $payload = json_decode($this->request->getContent());
        if (isset($payload->order)) {
            $order = $this->manager->getRepository(Order::class)->find(preg_replace('/\D/', '', $payload->order));
            $financialOrder = $this->orderService->resolveFinancialOrder($order);
            $this->createOrderInvoice($financialOrder, $invoice,  $invoice->getPrice());
        }
        //$this->braspagService->split($invoice);
        $this->refreshWalletValue($invoice);
        // api-platform-people#14: aggregate commission/royalties when services are wired
        $this->dispatchPeriodicReceivables($invoice);
    }

    /**
     * Optional hook for PeriodicReceivableDispatcher (api-platform-people#14).
     * Fail-open: missing wiring or exceptions must not break invoice creation.
     */
    private function dispatchPeriodicReceivables(Invoice $invoice): void
    {
        $dispatcher = $this->periodicReceivableDispatcher;
        if ($dispatcher === null || !is_object($dispatcher) || !method_exists($dispatcher, 'dispatch')) {
            return;
        }
        try {
            $dispatcher->dispatch($invoice);
        } catch (\Throwable $e) {
            // Fail-open — log only when a logger becomes available on this service
        }
    }
    private function refreshWalletValue(Invoice $invoice)
    {
        $destination_wallet = $invoice->getDestinationWallet();
        $souce_wallet = $invoice->getSourceWallet();

        if ($destination_wallet) {
            $destination_wallet->setBalance($destination_wallet->getBalance() + $invoice->getPrice());
            $this->manager->persist($destination_wallet);
        }

        if ($souce_wallet) {
            $souce_wallet->setBalance($souce_wallet->getBalance() - $invoice->getPrice());
            $this->manager->persist($souce_wallet);
        }

        $this->manager->flush();
    }

    public function payOrder(Order $order)
    {
        $order = $this->manager->getRepository(Order::class)->find($order->getId());
        $financialOrder = $this->orderService->resolveFinancialOrder($order);
        $orderStatus = $financialOrder->getStatus()->getRealStatus();
        if ($orderStatus == 'canceled') return;
        $paidValue = 0;
        foreach ($financialOrder->getInvoice() as $orderInvoice) {
            $invoice = $orderInvoice->getInvoice();
            if ($invoice->getstatus()->getRealStatus() == 'closed')
                $paidValue += $invoice->getPrice();
        }

        if ($paidValue > 0 && $paidValue >= $financialOrder->getPrice()) {
            $visitedOrderIds = [];
            $convertedSaleOrders = [];
            $this->markOrderTreeAsPaid($financialOrder, $visitedOrderIds, $convertedSaleOrders);
            $this->manager->flush();

            foreach ($convertedSaleOrders as $convertedSaleOrder) {
                $this->orderService->dispatchOrderCreated($convertedSaleOrder);
            }
        }
    }

    private function markOrderTreeAsPaid(
        Order $order,
        array &$visitedOrderIds = [],
        array &$convertedSaleOrders = []
    ): void
    {
        if (!$order->getId() || isset($visitedOrderIds[$order->getId()])) {
            return;
        }

        $visitedOrderIds[$order->getId()] = true;
        if ($this->orderService->convertDraftOrderToSale($order)) {
            $convertedSaleOrders[$order->getId()] = $order;
        }

        // Payment alone only closes the order when no delivery or production work is still pending.
        $order->setStatus($this->orderService->resolvePostPaymentStatus($order));
        $this->manager->persist($order);
        $this->orderProductQueueService->syncByOrderStatus($order);

        $linkedOrders = $this->manager->getRepository(Order::class)->findBy([
            'mainOrderId' => $order->getId(),
        ]);

        foreach ($linkedOrders as $linkedOrder) {
            if (!$linkedOrder instanceof Order) {
                continue;
            }

            $this->markOrderTreeAsPaid($linkedOrder, $visitedOrderIds, $convertedSaleOrders);
        }
    }

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {

        $companies = array_map(
            static fn (People $company): int => (int) $company->getId(),
            $this->peopleService->getMyCompanies()
        );

        if ($companies === []) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        if ($order = $this->request->query->get('orderId', null)) {
            $queryBuilder->join(sprintf('%s.order', $rootAlias), 'OrderInvoice');
            $queryBuilder->andWhere(sprintf('OrderInvoice.order IN(:order)', $rootAlias, $rootAlias));
            $queryBuilder->setParameter('order', $order);
        }

        $queryBuilder->andWhere(sprintf('(%s.payer IN(:companies) OR %s.receiver IN(:companies))', $rootAlias, $rootAlias));
        $queryBuilder->setParameter('companies', $companies);

        if ($payer = $this->request->query->get('payer', null)) {
            $queryBuilder->andWhere(sprintf('%s.payer IN(:payer)', $rootAlias));
            $queryBuilder->setParameter('payer', (int) preg_replace("/[^0-9]/", "", $payer));
        }

        if ($receiver = $this->request->query->get('receiver', null)) {
            $queryBuilder->andWhere(sprintf('%s.receiver IN(:receiver)', $rootAlias));
            $queryBuilder->setParameter('receiver', (int) preg_replace("/[^0-9]/", "", $receiver));
        }

        $ownTransfers = $this->request->query->get('ownTransfers', null);
        if (in_array($ownTransfers, ['1', 1, true, 'true'], true)) {
            $queryBuilder->andWhere(sprintf('%s.payer = %s.receiver', $rootAlias, $rootAlias));
        }

        $excludeOwnTransfers = $this->request->query->get('excludeOwnTransfers', null);
        if (in_array($excludeOwnTransfers, ['1', 1, true, 'true'], true)) {
            $queryBuilder->andWhere(sprintf('(%s.payer IS NULL OR %s.receiver IS NULL OR %s.payer <> %s.receiver)', $rootAlias, $rootAlias, $rootAlias, $rootAlias));
        }
    }


    /**
     * Marks unpaid/non-canceled invoices past dueDate as overdue (em atraso).
     * Idempotent: skips invoices already overdue/closed/canceled.
     *
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

    private function isCanceledStatus(?Status $status): bool
    {
        if (!$status instanceof Status) {
            return false;
        }

        $normalizedStatus = strtolower(trim((string) $status->getStatus()));
        $normalizedRealStatus = strtolower(trim((string) $status->getRealStatus()));

        return in_array($normalizedStatus, ['canceled', 'cancelled'], true)
            || in_array($normalizedRealStatus, ['canceled', 'cancelled'], true);
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
