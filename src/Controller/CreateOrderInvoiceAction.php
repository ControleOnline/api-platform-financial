<?php

namespace App\Controller;

use App\Entity\Status;
use App\Entity\Order;
use ControleOnline\Entity\PayInvoice;
use App\Entity\PurchasingOrderInvoice;
use ControleOnline\Entity\ReceiveInvoice;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderInvoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class CreateOrderInvoiceAction
{
    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->manager = $entityManager;
    }

    public function __invoke(Order $data, Request $request): Order
    {
        $payload = json_decode($request->getContent(), true);

        if (!isset($payload['price']) || !is_numeric($payload['price']))
            throw new \Exception('Invoice payment value is not defined', 400);

        if (!isset($payload['dueDate']))
            throw new \Exception('Invoice payment value is not defined', 400);
        else {
            if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $payload['dueDate']) !== 1)
                throw new \Exception('Invoice due date is not valid', 400);
        }

        // create invoice

        $invoice = $data instanceof SalesOrder ? (new ReceiveInvoice) : (new PayInvoice);

        $invoice->setPrice($payload['price']);
        $invoice->setDueDate(\DateTime::createFromFormat('Y-m-d', $payload['dueDate']));
        $invoice->setStatus(
            $this->manager->getRepository(Status::class)->findOneBy(['status' => 'waiting payment', 'context' => 'invoice'])
        );
        $invoice->setNotified(false);

        $this->manager->persist($invoice);

        // create order invoice

        $orderInvoice = $data instanceof SalesOrder ? (new SalesOrderInvoice) : (new PurchasingOrderInvoice);

        $orderInvoice->setOrder($data);
        $orderInvoice->setInvoice($invoice);

        $this->manager->persist($orderInvoice);

        return $data;
    }
}
