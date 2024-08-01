<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Braspag\Split\Domains\Sale\SplitPayments;
use Braspag\Split\Request\Sale as RequestSale;
use Braspag\Split\Domains\Environment;
use Braspag\Split\Domains\Authentication;
use ControleOnline\Entity\Config;
use ControleOnline\Entity\People;

class BraspagService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleRoleService $peopleRoleService
    ) {
    }

    public function split(Invoice $invoice)
    {
        $subordinateMerchantId = $this->getSubordinateMerchantId($invoice);
        $payments = $this->getPayments($invoice);
        $mainCompany = $this->peopleRoleService->getMainCompany();

        if (!$subordinateMerchantId || !$payments || $mainCompany->getId() == $invoice->getReceiver()->getId()) return;
        $percentage = 5;
        foreach ($payments as $payment) {

            $splitOne = new SplitPayments;
            $splitOne->setSubordinateMerchantId($subordinateMerchantId);
            $splitOne->setAmount($payment->amount / 100 * $percentage); /* Valor em centavos */
            $splitOne->setFares($percentage, $percentage);

            /* Executa o split */
            $result = $this->getInstance($mainCompany)->split($payment->id, [$splitOne]);
            $response[] = $result->getResponseRaw();
        }

        $invoice->addOtherInformations('braspag', $response);
        $this->manager->persist($invoice);
        $this->manager->flush();
    }

    /* PaymentId da transação já capturada */
    private function getPayments(Invoice $invoice)
    {
        $OtherInformations = $invoice->getOtherInformations(true);
        return isset($OtherInformations->lio) ? $OtherInformations->lio->result->payments : null;
    }

    private function getSubordinateMerchantId(Invoice $invoice)
    {
        return $this->getConfig($invoice->getReceiver(), 'merchant-id');
    }

    private function getConfig(People $people, $key)
    {
        return $this->manager->getRepository(Config::class)->findOneBy([
            'people' => $people,
            'config_key' => 'braspag-' . $key
        ]);
    }

    private function  getInstance($mainCompany)
    {
        $merchantKey = $this->getConfig($mainCompany, 'merchant-id');
        $env = Environment::production($merchantKey, $this->getConfig($mainCompany, 'client-sercret'));
        $auth = new Authentication($env);
        return  new RequestSale($env, $merchantKey, $auth);
    }
}
