<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use ControleOnline\Entity\PaymentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Wallet;

class InvoiceService
{
    private $request;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $peopleService,
        private RequestStack $requestStack,
        private BraspagService $braspagService

    ) {
        $this->request = $this->requestStack->getCurrentRequest();
    }


    public function createInvoice(Order $order, $price, $dueDate, Wallet $wallet, $portion = 1, $installments = 1, $installment_id =  null)
    {
        $paymentType = $this->manager->getRepository(PaymentType::class)->find(1);

        $status = $this->manager->getRepository(Status::class)->findOneBy([
            'status' => 'waiting payment',
            'context' => 'invoice'
        ]);
        $invoice = new Invoice();
        $invoice->setPayer($order->getPayer());
        $invoice->setReceiver($order->getProvider());
        $invoice->setPrice($price);
        $invoice->setDueDate(new \DateTime($dueDate));
        $invoice->setWallet($wallet);
        $invoice->setPortion($portion);
        $invoice->setInstallments($installments);
        $invoice->setInstallmentId($installment_id);
        $invoice->setStatus($status);
        $invoice->setPaymentType($paymentType);
        $this->manager->persist($invoice);
        $this->createOrderInvoice($order, $invoice, $price);
        $this->manager->flush();
        return $invoice;
    }
    public function createOrderInvoice(Order $order, Invoice $invoice, $price = 0)
    {
        $orderInvoice = new OrderInvoice();
        $orderInvoice->setOrder($order);
        $orderInvoice->setInvoice($invoice);
        $orderInvoice->setRealPrice($price);

        $this->manager->persist($orderInvoice);
        $this->manager->flush();
        $this->payOrder($order);
        return $orderInvoice;
    }

    public function afterPersist(Invoice $invoice)
    {
        $payload   = json_decode($this->request->getContent());
        if (isset($payload->order)) {
            $order = $this->manager->getRepository(Order::class)->find(preg_replace('/\D/', '', $payload->order));
            $this->createOrderInvoice($order, $invoice,  $order->getPrice());
        }
        //$this->braspagService->split($invoice);
    }

    public function payOrder(Order $order)
    {
        $order = $this->manager->getRepository(Status::class)->find($order->getId());
        $orderStatus = $order->getStatus()->getStatus();
        if ($orderStatus != 'waiting payment') return;
        $paidValue = 0;
        foreach ($order->getInvoice() as $orderInvoice) {
            $invoice = $orderInvoice->getInvoice();
            if ($invoice->getstatus()->getstatus() == 'paid')
                $paidValue += $invoice->getPrice();
        }

        if ($paidValue >= $order->getPrice()) {

            $status = $this->manager->getRepository(Status::class)->findOneBy([
                'context' => 'order',
                'status' => 'paid'
            ]);

            $order->setStatus($status);
            $this->manager->persist($order);
            $this->manager->flush();
        }
    }

    public function secutiryFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
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
    }
}
