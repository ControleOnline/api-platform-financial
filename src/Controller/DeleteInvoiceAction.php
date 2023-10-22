<?php

namespace ControleOnline\Controller;

use ControleOnline\Controller\AbstractCustomResourceAction;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\SalesOrder;

class DeleteInvoiceAction extends AbstractCustomResourceAction
{
  public function index(): ?array
  {
    try {

      $this->manager()->getConnection()->beginTransaction();

      $invoice = $this->entity(ReceiveInvoice::class, $this->payload()->id);
      if ($invoice === null) {
        throw new \Exception('Invoice was not found');
      }

      $order  = $invoice->getOneOrder() !== null ? $invoice->getOneOrder()->getId() : null;
      $status = $this->manager()->getRepository(Status::class)
        ->findOneBy([
          'status' => 'canceled'
        ]);

      $this->manager()->persist($invoice->setStatus($status));

      $this->manager()->flush();
      $this->manager()->getConnection()->commit();

      // update order total

      if ($order !== null) {
        $this->manager()->getRepository(SalesOrder::class)
          ->updateOrderTotalFromInvoicesPrice($order);
      }

      return null;

    } catch (\Exception $e) {
      if ($this->manager()->getConnection()->isTransactionActive()) {
        $this->manager()->getConnection()->rollBack();
      }

      throw new \Exception($e->getMessage());
    }
  }
}
