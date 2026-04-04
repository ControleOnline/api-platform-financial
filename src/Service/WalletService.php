<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\PaymentType;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Wallet;
use ControleOnline\Entity\WalletPaymentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 AS Security;
use Doctrine\ORM\QueryBuilder;

class WalletService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $peopleService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $this->peopleService->checkCompany('people', $queryBuilder, $resourceClass, $applyTo, $rootAlias);
    }

    public function discoverWalletPaymentType(Wallet $wallet, PaymentType $paymentType, $paymentCode = null) {

        $walletPaymentType = $this->manager->getRepository(WalletPaymentType::class)->findOneBy([
            'wallet' => $wallet,
            'paymentType' => $paymentType,
            'paymentCode' => $paymentCode 
        ]);

        if (!$walletPaymentType) {
            $walletPaymentType = new WalletPaymentType();
            $walletPaymentType->setPaymentType($paymentType);
            $walletPaymentType->setWallet($wallet);
            $walletPaymentType->setPaymentCode($paymentCode);
            $this->manager->persist($walletPaymentType);
            $this->manager->flush();
        }

        return $walletPaymentType;

    }

    public function discoverPaymentType(People $company, array $paymentTypeData): PaymentType
    {
        $paymentTypeName = $paymentTypeData['paymentType'] ?? $paymentTypeData['name'] ?? null;

        if (!$paymentTypeName) {
            throw new \InvalidArgumentException('Payment type name is required.');
        }

        $paymentType = $this->manager->getRepository(PaymentType::class)->findOneBy([
            'people' => $company,
            'paymentType' => $paymentTypeName
        ]);

        if (!$paymentType) {
            $paymentType = new PaymentType();
            $paymentType->setPeople($company);
            $paymentType->setPaymentType($paymentTypeName);
            $paymentType->setFrequency($paymentTypeData['frequency'] ?? 'single');
            $paymentType->setInstallments($paymentTypeData['installments'] ?? 'single');

            $this->manager->persist($paymentType);
            $this->manager->flush();
        }

        return $paymentType;
    }
    public function discoverWallet(People $people, $wallet_name)
    {
        $wallet = $this->manager->getRepository(Wallet::class)->findOneBy([
            'people' => $people,
            'wallet' => $wallet_name
        ]);
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->setPeople($people);
            $wallet->setWallet($wallet_name);
            $wallet->setBalance(0);
            $this->manager->persist($wallet);
            $this->manager->flush();
        }
        return $wallet;
    }
}
