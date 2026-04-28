<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use ControleOnline\Service\ConfigService;
use ControleOnline\Service\DeviceService;
use ControleOnline\Service\InFlowService;
use ControleOnline\Service\IntegrationService;
use ControleOnline\Service\PrintService;
use ControleOnline\Service\RequestPayloadService;
use ControleOnline\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class CashRegisterServiceTest extends TestCase
{
    public function testOpenUsesLatestInvoiceIdWhenHistoryExists(): void
    {
        $provider = $this->createMock(People::class);
        $device = $this->createConfiguredMock(Device::class, [
            'getDevice' => 'pdv-1',
        ]);
        $invoice = $this->createConfiguredMock(Invoice::class, [
            'getId' => 42,
        ]);
        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(
                [
                    'receiver' => $provider,
                    'device' => $device,
                ],
                ['id' => 'DESC']
            )
            ->willReturn($invoice);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Invoice::class)
            ->willReturn($repository);

        $deviceService = $this->createMock(DeviceService::class);
        $deviceService
            ->expects(self::once())
            ->method('addDeviceConfigs')
            ->with(
                $provider,
                [
                    'cash-wallet-open-id' => 42,
                    'cash-wallet-closed-id' => 0,
                ],
                'pdv-1',
                'PDV'
            );

        $service = $this->createService($entityManager, $deviceService);
        $service->open($device, $provider);
    }

    public function testOpenStartsFromZeroWhenThereIsNoInvoiceHistory(): void
    {
        $provider = $this->createMock(People::class);
        $device = $this->createConfiguredMock(Device::class, [
            'getDevice' => 'pdv-1',
        ]);
        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(
                [
                    'receiver' => $provider,
                    'device' => $device,
                ],
                ['id' => 'DESC']
            )
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Invoice::class)
            ->willReturn($repository);

        $deviceService = $this->createMock(DeviceService::class);
        $deviceService
            ->expects(self::once())
            ->method('addDeviceConfigs')
            ->with(
                $provider,
                [
                    'cash-wallet-open-id' => 0,
                    'cash-wallet-closed-id' => 0,
                ],
                'pdv-1',
                'PDV'
            );

        $service = $this->createService($entityManager, $deviceService);
        $service->open($device, $provider);
    }

    private function createService(
        EntityManagerInterface $entityManager,
        DeviceService $deviceService
    ): CashRegisterService {
        return new CashRegisterService(
            $entityManager,
            $this->createMock(PrintService::class),
            $this->createMock(ConfigService::class),
            $this->createMock(InFlowService::class),
            $deviceService,
            $this->createMock(IntegrationService::class),
            $this->createMock(WhatsAppService::class),
            $this->createMock(RequestPayloadService::class)
        );
    }
}
