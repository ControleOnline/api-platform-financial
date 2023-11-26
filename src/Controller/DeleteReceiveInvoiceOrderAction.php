<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Config;
use App\Entity\People;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\SalesOrder;
use ControleOnline\Entity\SalesOrderInvoice;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Library\Itau\ItauClient;

class DeleteReceiveInvoiceOrderAction
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

    public function __invoke(ReceiveInvoice $data, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        try {
            if (!isset($payload['orderId']) || empty($payload['orderId']))
                throw new \Exception('Order ID is not defined', 400);

            $order = $this->manager->getRepository(SalesOrder::class)->find($payload['orderId']);
            if ($order === null)
                throw new \Exception('Order not found', 404);

            if (!$this->removeOrderIsAllowed($data, $order))
                throw new \Exception('This order can not be removed', 400);

            $orderInvoice = $this->manager->getRepository(SalesOrderInvoice::class)
                ->findOneBy([
                    'invoice' => $data,
                    'order'   => $order,
                ]);

            if ($orderInvoice === null)
                throw new \Exception('This order does not belong to this invoice', 404);

            $this->manager->getConnection()->beginTransaction();

            // remove order invoice

            $this->manager->remove($orderInvoice);
            $this->manager->flush();

            // recalculate invoice price

            $this->recalculateInvoicePrice($data);

            $this->manager->getConnection()->commit();

            return new JsonResponse([
                'response' => [
                    'data'    => null,
                    'count'   => 1,
                    'error'   => '',
                    'success' => true,
                ],
            ]);
        } catch (\Exception $e) {
            if ($this->manager->getConnection()->isTransactionActive())
                $this->manager->getConnection()->rollBack();

            return new JsonResponse([
                'response' => [
                    'data'    => [],
                    'count'   => 0,
                    'error'   => $e->getMessage(),
                    'success' => false,
                ],
            ]);
        }
    }

    private function removeOrderIsAllowed(ReceiveInvoice $invoice, SalesOrder $order): bool
    {
        if ($invoice->getStatus()->getRealStatus() === 'pending')
            return true;

        if (!$this->isBilletCreated($invoice, $order))
            return true;

        return false;
    }

    private function isBilletCreated(ReceiveInvoice $invoice, SalesOrder $order): bool
    {
        if ($invoice->getOrder()->isEmpty())
            throw new \Exception('Invoice orders not found');

        $configs = $this->getItauConfig($order->getProvider());
        $payment = (new ItauClient($invoice, $configs))->getPayment();

        return $payment->getPaymentType() === 'billet' && $payment->getStatus() === 'created';
    }

    private function getItauConfig(People $people): array
    {
        /**
         * @var \ControleOnline\Repository\ConfigRepository
         */
        $crepo   = $this->manager->getRepository(Config::class);
        $configs = $crepo->getItauConfigByPeople($people);

        if ($configs === null)
            return [];

        return $configs;
    }

    private function recalculateInvoicePrice(ReceiveInvoice $invoice)
    {
        $price = 0;

        /**
         * @var \ControleOnline\Entity\SalesOrderInvoice $salesOrderInvoice
         */
        foreach ($invoice->getOrder() as $salesOrderInvoice) {
            if (!in_array($salesOrderInvoice->getOrder()->getStatus()->getStatus(), ['canceled', 'expired'])) {
                $price = $price + ($salesOrderInvoice->getRealPrice() > 0 ? $salesOrderInvoice->getRealPrice() :$salesOrderInvoice->getOrder()->getPrice());
            }
        }

        $this->manager->persist(
            $invoice->setPrice($price)
        );

        $this->manager->flush();
    }
}
