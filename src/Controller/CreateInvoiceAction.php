<?php

namespace ControleOnline\Controller;

use App\Controller\AbstractCustomResourceAction;
use App\Entity\Status;
use App\Entity\People;
use App\Entity\SalesOrder;
use App\Entity\PurchasingOrder;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\PayInvoice;
use App\Entity\SalesOrderInvoice;
use App\Entity\PurchasingOrderInvoice;
use App\Entity\Category;

class CreateInvoiceAction extends AbstractCustomResourceAction
{
  public function index(): ?array
  {
    try {

      $this->manager()->getConnection()->beginTransaction();

      $ostatus  = $this->manager()->getRepository(Status::class  )->findOneBy(['status' => 'delivered']);
      $istatus  = $this->manager()->getRepository(Status::class)->findOneBy(['status' => ['waiting payment']]);
      $company  = $this->manager()->getRepository(People::class)->find($this->payload()->company);
      $category = $this->manager()->getRepository(Category::class)
        ->findOneBy([
          'id'      => $this->payload()->category,
          'company' => $this->payload()->company
        ]);
      $provider = $this->manager()->getRepository(People::class)->find($this->payload()->provider);

      if ($company === null) {
        throw new \Exception('Company was not found');
      }

      if ($category === null) {
        throw new \Exception('Category was not found');
      }

      if ($provider === null) {
        throw new \Exception('Provider was not found');
      }

      $orderClass        = $this->payload()->orderType == 'purchase' ? '\\App\\Entity\\PurchasingOrder'        : '\\App\\Entity\\SalesOrder';
      $invoiceClass      = $this->payload()->orderType == 'purchase' ? '\\ControleOnline\\Entity\\PayInvoice'             : '\\App\\Entity\\ReceiveInvoice';
      $orderInvoiceClass = $this->payload()->orderType == 'purchase' ? '\\App\\Entity\\PurchasingOrderInvoice' : '\\App\\Entity\\SalesOrderInvoice';

      // create order

      ($order = new $orderClass())
        ->setStatus  ($ostatus)
        ->setClient  ($company)
        ->setProvider($provider)
        ->setPayer   ($company)
        ->setPrice   ($this->payload()->amount)
      ;

      $this->manager()->persist($order);

      // create first payment

      $firstInvoice = new $invoiceClass();
      $firstInvoice->setPrice      ($this->payload()->amount);
      $firstInvoice->setDueDate    (\DateTime::createFromImmutable($this->payload()->dueDate));
      $firstInvoice->setStatus     ($istatus);
      $firstInvoice->setNotified   (false);
      $firstInvoice->setCategory   ($category);
      $firstInvoice->setDescription($this->payload()->description);
      $firstInvoice->setPaymentMode($this->payload()->paymentMode);

      $this->manager()->persist($firstInvoice);

      $orderInvoice = new $orderInvoiceClass();
      $orderInvoice->setInvoice($firstInvoice);
      $orderInvoice->setOrder  ($order);

      $this->manager()->persist($orderInvoice);

      if ($this->payload()->isParceled()) {

        // calculate value of every parcel

        $amount = $this->payload()->amount / $this->payload()->paymentMode;

        // update first invoice

        $this->manager()->persist($firstInvoice->setPrice($amount));

        // calculate initial duedate

        $duedate = \DateTime::createFromImmutable($this->payload()->dueDate)
          ->modify('+1 month');

        // create invoices

        for ($p = 2; $p <= $this->payload()->paymentMode; $p++) {
          $invoice = new $invoiceClass();
          $invoice->setPrice      ($amount);
          $invoice->setDueDate    ((clone $duedate));
          $invoice->setStatus     ($istatus);
          $invoice->setNotified   (false);
          $invoice->setCategory   ($firstInvoice->getCategory());
          $invoice->setDescription($firstInvoice->getDescription());
          $invoice->setPaymentMode($firstInvoice->getPaymentMode());

          $this->manager()->persist($invoice);

          $orderInvoice = new $orderInvoiceClass();
          $orderInvoice->setInvoice($invoice);
          $orderInvoice->setOrder  ($order);

          $this->manager()->persist($orderInvoice);

          // get next duedate

          $duedate = $duedate->modify('+1 month');
        }
      }

      $this->manager()->flush();
      $this->manager()->getConnection()->commit();

      return [
        'order' => $order->getId(),
      ];

    } catch (\Exception $e) {
      if ($this->manager()->getConnection()->isTransactionActive()) {
        $this->manager()->getConnection()->rollBack();
      }

      throw new \Exception($e->getMessage());
    }
  }
}
