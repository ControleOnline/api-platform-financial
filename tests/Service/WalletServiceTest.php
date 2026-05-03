<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\PaymentType;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Wallet;
use ControleOnline\Entity\WalletPaymentType;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class WalletServiceTest extends TestCase
{
    public function testDiscoverWalletPaymentTypeReusesExistingAssociationRegardlessOfPaymentCode(): void
    {
        $wallet = new Wallet();
        $wallet->setWallet('99 Food');
        $wallet->setPeople($this->createMock(People::class));

        $paymentType = new PaymentType();
        $paymentType->setPaymentType('99 Food - Repasse semanal');

        $existingWalletPaymentType = new WalletPaymentType();
        $existingWalletPaymentType->setWallet($wallet);
        $existingWalletPaymentType->setPaymentType($paymentType);
        $existingWalletPaymentType->setPaymentCode('legacy_code');

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'wallet' => $wallet,
                'paymentType' => $paymentType,
            ])
            ->willReturn($existingWalletPaymentType);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(WalletPaymentType::class)
            ->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService($entityManager);
        $resolvedWalletPaymentType = $service->discoverWalletPaymentType(
            $wallet,
            $paymentType,
            'weekly_settlement'
        );

        self::assertSame($existingWalletPaymentType, $resolvedWalletPaymentType);
        self::assertSame('legacy_code', $resolvedWalletPaymentType->getPaymentCode());
    }

    public function testDiscoverWalletPaymentTypeBackfillsMissingPaymentCode(): void
    {
        $wallet = new Wallet();
        $wallet->setWallet('99 Food');
        $wallet->setPeople($this->createMock(People::class));

        $paymentType = new PaymentType();
        $paymentType->setPaymentType('99 Food - Taxa de servico');

        $existingWalletPaymentType = new WalletPaymentType();
        $existingWalletPaymentType->setWallet($wallet);
        $existingWalletPaymentType->setPaymentType($paymentType);
        $existingWalletPaymentType->setPaymentCode(null);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'wallet' => $wallet,
                'paymentType' => $paymentType,
            ])
            ->willReturn($existingWalletPaymentType);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(WalletPaymentType::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($existingWalletPaymentType);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($entityManager);
        $resolvedWalletPaymentType = $service->discoverWalletPaymentType(
            $wallet,
            $paymentType,
            'service_fee'
        );

        self::assertSame($existingWalletPaymentType, $resolvedWalletPaymentType);
        self::assertSame('service_fee', $resolvedWalletPaymentType->getPaymentCode());
    }

    private function createService(EntityManagerInterface $entityManager): WalletService
    {
        return new WalletService(
            $entityManager,
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(PeopleService::class)
        );
    }
}
