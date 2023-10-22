<?php

namespace ControleOnline\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\Status;

class RenewInvoiceAction
{

  public function __construct(EntityManagerInterface $manager)
  {
    $this->entityManager  = $manager;
  }
  public function __invoke(ReceiveInvoice $data, Request $request): JsonResponse

  {
    try {

      $this->entityManager->getConnection()->beginTransaction();
      $invoiceId = $request->get('id', null);
      if ($invoiceId === null) {
        throw new \Exception('Invoice was not found');
      }

      $data = $this->entityManager->getRepository(ReceiveInvoice::class)
        ->find($invoiceId);

      if ($data === null) {
        throw new \Exception('Invoice was not found');
      }

      $newInvoice = clone $data;
      $this->entityManager->detach($newInvoice);



      $datetime = new \DateTime('now');
      $datetime->modify('+1 work day');
      $newInvoice->setDueDate($datetime);
      $newInvoice->setStatus($this->entityManager->getRepository(Status::class)->findOneBy(['status' => ['open'], 'context' => 'invoice']));

      $this->entityManager->persist($newInvoice);
      $this->entityManager->flush();



      foreach ($data->getOrder() as $order) {

        $newOrder = clone $order;
        $this->entityManager->detach($newOrder);

        $newOrder->setInvoice($newInvoice);

        $this->entityManager->persist($newOrder);
        $this->entityManager->flush();

        //$newInvoice->addOrder( $order);
      }

      $this->entityManager->getConnection()->commit();


      return new JsonResponse([
        'response' => [
          'data'    => [
            'id' => $newInvoice->getId()
          ],
          'count'   => 1,
          'error'   => '',
          'success' => true,
        ],
      ]);
    } catch (\Exception $e) {
      if ($this->entityManager->getConnection()->isTransactionActive()) {
        $this->entityManager->getConnection()->rollBack();
      }

      throw new \Exception($e->getMessage());
    }
  }
}
