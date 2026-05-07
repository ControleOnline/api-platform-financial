<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use ControleOnline\Entity\PaymentType;
use ControleOnline\Entity\People;
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
        private OrderProductQueueService $orderProductQueueService

    ) {
        $this->request = $this->requestStack->getCurrentRequest();
    }

    public function setPayed(Invoice $invoice)
    {
        $status = $this->statusService->discoveryStatus(
            'closed',
            'paid',
            'invoice'
        );
        $invoice->setStatus($status);
        $this->manager->persist($invoice);
        $this->manager->flush();
        return $invoice;
    }

    public function syncItauPaymentStatus(Invoice $invoice, bool $promisePaid, bool $paid): void
    {
        if ($promisePaid) {
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

            $status = $this->statusService->discoveryRealStatus(
                'open',
                'order',
                'paid'
            );


            $this->markOrderTreeAsPaid($financialOrder, $status);
            $this->manager->flush();
            $this->orderProductQueueService->syncByOrderStatus($financialOrder);
        }
    }

    private function markOrderTreeAsPaid(Order $order, Status $status, array &$visitedOrderIds = []): void
    {
        if (!$order->getId() || isset($visitedOrderIds[$order->getId()])) {
            return;
        }

        $visitedOrderIds[$order->getId()] = true;
        $this->orderService->convertDraftOrderToSale($order);
        $order->setStatus($status);
        $this->manager->persist($order);
        $this->orderProductQueueService->syncByOrderStatus($order);

        $linkedOrders = $this->manager->getRepository(Order::class)->findBy([
            'mainOrderId' => $order->getId(),
        ]);

        foreach ($linkedOrders as $linkedOrder) {
            if (!$linkedOrder instanceof Order) {
                continue;
            }

            $this->markOrderTreeAsPaid($linkedOrder, $status, $visitedOrderIds);
        }
    }

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {

        if ($order = $this->request->query->get('orderId', null)) {
            $queryBuilder->join(sprintf('%s.order', $rootAlias), 'OrderInvoice');
            $queryBuilder->andWhere(sprintf('OrderInvoice.order IN(:order)', $rootAlias, $rootAlias));
            $queryBuilder->setParameter('order', $order);
        }

        $companies   = $this->peopleService->getMyCompanies();
        $queryBuilder->andWhere(sprintf('%s.payer IN(:companies) OR %s.receiver IN(:companies)', $rootAlias, $rootAlias));
        $queryBuilder->setParameter('companies', $companies);

        if ($payer = $this->request->query->get('payer', null)) {
            $queryBuilder->andWhere(sprintf('%s.payer IN(:payer)', $rootAlias));
            $queryBuilder->setParameter('payer', preg_replace("/[^0-9]/", "", $payer));
        }

        if ($receiver = $this->request->query->get('receiver', null)) {
            $queryBuilder->andWhere(sprintf('%s.receiver IN(:receiver)', $rootAlias));
            $queryBuilder->setParameter('receiver', preg_replace("/[^0-9]/", "", $receiver));
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
}
