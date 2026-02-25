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
        private OrderPrintService $orderPrintService

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
        $installment_id =  null
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
        $this->manager->persist($invoice);
        if ($order)
            $this->createOrderInvoice($order, $invoice, $price);
        $this->manager->flush();
        return $invoice;
    }

    public function createInvoiceByOrder(Order $order, $price, ?Status $status = null, DateTime $dueDate, ?Wallet $source_wallet = null, ?Wallet $destination_wallet = null, $portion = 1, $installments = 1, $installment_id =  null): Invoice
    {

        if (!$source_wallet && !$destination_wallet)
            throw new Exception("Need a source or destination Wallet", 301);
        $status = $this->statusService->discoveryStatus(
            'pending',
            'waiting payment',
            'invoice'
        );
        return $this->createInvoice($order, $order->getPayer() ?: $order->getClient(), $order->getProvider(), $price, $status, $dueDate,  $source_wallet, $destination_wallet, $portion, $installments, $installment_id);
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
            $this->createOrderInvoice($order, $invoice,  $order->getPrice());
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
        $orderStatus = $order->getStatus()->getRealStatus();
        if ($orderStatus == 'canceled') return;
        $paidValue = 0;
        foreach ($order->getInvoice() as $orderInvoice) {
            $invoice = $orderInvoice->getInvoice();
            if ($invoice->getstatus()->getRealStatus() == 'closed')
                $paidValue += $invoice->getPrice();
        }

        if ($paidValue > 0 && $paidValue >= $order->getPrice()) {

            $status = $this->statusService->discoveryRealStatus(
                'open',
                'order',
                'paid'
            );


            $order->setStatus($status);
            $this->orderPrintService->printOrder($order);

            $this->manager->persist($order);
            $this->manager->flush();
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
}
