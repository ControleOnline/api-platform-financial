<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Wallet;

class InvoiceService
{
    private $request;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $peopleService,
        RequestStack $requestStack

    ) {
        $this->request  = $requestStack->getCurrentRequest();
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


        $orderInvoice = new OrderInvoice();
        $orderInvoice->setOrder($order);
        $orderInvoice->setInvoice($invoice);
        $orderInvoice->setRealPrice($price);

        $this->manager->persist($orderInvoice);
        $this->manager->persist($invoice);

        $this->manager->flush();
        return $invoice;
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
