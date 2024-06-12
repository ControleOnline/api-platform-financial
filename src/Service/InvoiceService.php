<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security
    ) {
    }

    public function afterPersist(Invoice $Invoice)
    {

        foreach ($Invoice->getOrder() as $OrderInvoice) {

            $invoice = $OrderInvoice->getInvoice();
            $order = $OrderInvoice->getOrder();
            if ($invoice->getStatus()->getStatus() == 'Pago') {
                $orderStatus = $this->manager->getRepository(Status::class)->findOneBy([
                    'status' => 'Aguardando Pagamento',
                    'context' => 'order'
                ]);
                $order->setStatus($orderStatus);
                $this->manager->persist($order);
                $this->manager->flush();
            }
        }
        return $Invoice;
    }
}
