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
        $paymentId = $this->getPaymentId($invoice);

        if (!$subordinateMerchantId || !$paymentId)
            return;

        $splitOne = new SplitPayments;
        $splitOne->setSubordinateMerchantId($subordinateMerchantId);
        $splitOne->setAmount(3000); /* Valor em centavos */
        $splitOne->setFares(5, 0);

        /* Executa o split */
        $result = $this->getInstance()->split($paymentId, [$splitOne]);

        $invoice->addOtherInformations('braspag', $result->getResponseRaw());
        $this->manager->persist($invoice);
        $this->manager->flush();
    }

    /* PaymentId da transação já capturada */
    private function getPaymentId(Invoice $invoice)
    {
        $OtherInformations = $invoice->getOtherInformations(true);
        return isset($OtherInformations->lio) ? $OtherInformations->lio->seinao : null;
    }

    private function getSubordinateMerchantId(Invoice $invoice)
    {
        return $this->getConfig($invoice->getReceiver(), 'subordinate-merchant-id');
    }

    private function getConfig(People $people, $key)
    {
        return $this->manager->getRepository(Config::class)->findOneBy([
            'people' => $people,
            'config_key' => 'braspag-' . $key
        ]);
    }

    private function  getInstance()
    {
        $people = $this->peopleRoleService->getMainCompany();
        $merchantKey = $this->getConfig($people, 'merchant-key');
        $env = Environment::production($this->getConfig($people, 'client-id'), $this->getConfig($people, 'client-sercret'));
        $auth = new Authentication($env);
        return  new RequestSale($env, $merchantKey, $auth);
    }
}
