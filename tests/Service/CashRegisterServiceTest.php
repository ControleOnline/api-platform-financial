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
use Doctrine\ORM\EntityRepository;
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
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
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

        $integrationService = $this->createMock(IntegrationService::class);
        $integrationService
            ->expects(self::once())
            ->method('addManagerPushIntegrations')
            ->with(
                self::callback(static function (string $payload): bool {
                    $decoded = json_decode($payload, true);

                    return is_array($decoded)
                        && ($decoded['event'] ?? null) === 'cash.open'
                        && ($decoded['openedAtLabel'] ?? '') !== '';
                }),
                $provider
            )
            ->willReturn(1);

        $service = $this->createService($entityManager, $deviceService, $integrationService);
        $service->open($device, $provider);
    }

    public function testOpenStartsFromZeroWhenThereIsNoInvoiceHistory(): void
    {
        $provider = $this->createMock(People::class);
        $device = $this->createConfiguredMock(Device::class, [
            'getDevice' => 'pdv-1',
        ]);
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
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

        $integrationService = $this->createMock(IntegrationService::class);
        $integrationService
            ->expects(self::once())
            ->method('addManagerPushIntegrations')
            ->willReturn(0);

        $service = $this->createService($entityManager, $deviceService, $integrationService);
        $service->open($device, $provider);
    }

    private function createService(
        EntityManagerInterface $entityManager,
        DeviceService $deviceService,
        ?IntegrationService $integrationService = null
    ): CashRegisterService {
        return new CashRegisterService(
            $entityManager,
            $this->createMock(PrintService::class),
            $this->createMock(ConfigService::class),
            $this->createMock(InFlowService::class),
            $deviceService,
            $integrationService ?? $this->createMock(IntegrationService::class),
            $this->createMock(WhatsAppService::class),
            $this->createMock(RequestPayloadService::class)
        );
    }
}
