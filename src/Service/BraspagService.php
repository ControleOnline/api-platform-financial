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
        $key = 0;
        $split = [];
        foreach ($payments as $payment) {
            $value = floor($payment->amount / 100 * $percentage * 100); /* Valor em centavos */
            $split[$key] = new SplitPayments;
            $split[$key]->setSubordinateMerchantId($subordinateMerchantId);
            $split[$key]->setAmount($value);
            $split[$key]->setFares($percentage, $value);
            $key++;
        }

        /* Executa o split */
        $result = $this->getInstance($mainCompany)->split($payment->id, $split);
        $invoice->addOtherInformations('braspag', $result->getResponseRaw());
        $this->manager->persist($invoice);
        $this->manager->flush();
        return $result->getResponseRaw();
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
        $config = $this->manager->getRepository(Config::class)->findOneBy([
            'people' => $people,
            'configKey' => 'braspag-' . $key
        ]);

        return $config ? $config->getConfigValue() : null;
    }

    private function  getInstance($mainCompany)
    {
        $merchantKey = $this->getConfig($mainCompany, 'merchant-id');
        $env = Environment::production($merchantKey, $this->getConfig($mainCompany, 'client-sercret'));
        $auth = new Authentication($env);
        return  new RequestSale($env, $merchantKey, $auth);
    }
}
