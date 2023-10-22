<?php

namespace ControleOnline\Controller;

use ControleOnline\Controller\AbstractCustomResourceAction;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleProvider;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\SalesOrder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class UpdateInvoiceAction extends AbstractController
{

    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
   *
   * @Route("/invoices/{id}","PUT")
   */
  public function index(Request $request): JsonResponse
  {
    $idInvoice = $request->attributes->get('id');
    $payload = json_decode($request->getContent());
    // dd($payload);
    try {

      $this->manager->getConnection()->beginTransaction();

      $invoice = $this->manager->getRepository(ReceiveInvoice::class)->find($idInvoice);
      if ($invoice === null) {
        throw new \Exception('Invoice was not found');
      }

      if (($order = $invoice->getOneOrder()) === null) {
        throw new \Exception('Order was not found');
      }
      
      $company = $this->manager->getRepository(People::class)->find($payload->company);
      if ($company === null) {
        throw new \Exception('Company was not found');
      }

      // if update category

      if (isset($payload->category)) {
        $category = $this->manager->getRepository(Category::class)
          ->findOneBy([
            'id'      => $payload->category,
            'company' => $payload->company
          ]);

        if ($category === null) {
          throw new \Exception('Category was not found');
        }

        $this->manager->persist($invoice->setCategory($category));
      }

      // if update provider

      if (isset($payload->provider)) {
        $provider = $this->manager->getRepository(People::class)->find($payload->provider);

        if ($provider === null) {
          throw new \Exception('Provider was not found');
        }
        $this->manager->persist($order->setProvider($provider));
      }

      // if update amount

      if (isset($payload->amount)) {
        $this->manager->persist($invoice->setPrice($payload->amount));
      }

      // if update dueDate
      $formatedDate = DateTimeImmutable::createFromFormat("Y-m-d", $payload->dueDate);
      // dd(\DateTime::createFromImmutable($formatedDate));
      // dd($formatedDate);
      if (isset($payload->dueDate)) {
        $this->manager->persist(
          $invoice->setDueDate(
            \DateTime::createFromImmutable($formatedDate)
          )
        );
      }

      // if update description

      if (isset($payload->description)) {
        $this->manager->persist($invoice->setDescription($payload->description));
      }

      // if update status

      if (isset($payload->status)) {
        $istatus  = $this->manager->getRepository(Status::class)
          ->findOneBy(['status' => $payload->status]);

        if ($istatus === null) {
          throw new \Exception('Status was not found');
        }

        $this->manager->persist($invoice->setStatus($istatus));
      }

      $this->manager->flush();
      $this->manager->getConnection()->commit();

      // if update amount update order total

      if (isset($payload->amount)) {
        $this->manager->getRepository(SalesOrder::class)
          ->updateOrderTotalFromInvoicesPrice($order->getId());
      }

      return new JsonResponse([
        'id' => $invoice->getId(),
      ]);
    } catch (\Exception $e) {
      if ($this->manager->getConnection()->isTransactionActive()) {
        $this->manager->getConnection()->rollBack();
      }

      throw new \Exception($e->getMessage());
    }
  }
}
