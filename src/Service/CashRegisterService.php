<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Spool;
use ControleOnline\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CashRegisterService
{
    private string $pdvDeviceType = 'PDV';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrintService $printService,
        private ConfigService $configService,
        private InFlowService $inFlowService,
        private DeviceService $deviceService,
        private IntegrationService $integrationService,
        private WhatsAppService $whatsAppService,
        private RequestPayloadService $requestPayloadService,
        private ?TokenStorageInterface $security = null
    ) {}

    public function close(Device $device, People $provider)
    {
        $this->deviceService->addDeviceConfigs($provider, [
            'cash-wallet-closed-id' => $this->resolveLastInvoiceId($device, $provider),
        ], $device->getDevice(), $this->pdvDeviceType);

        $this->notify($device,  $provider);
        $this->integrationService->addManagerPushIntegrations(
            json_encode(
                [
                'store' => 'orders',
                'event' => 'cash.closed',
                'company' => (string) $provider->getId(),
                'provider' => (string) $provider->getId(),
                'providerName' => trim((string) ($provider->getName() ?: $provider->getAlias() ?: 'Loja')),
                'device' => $device->getDevice(),
                'deviceId' => (string) $device->getId(),
                'deviceAlias' => trim((string) ($device->getAlias() ?: $device->getDevice())),
                'notificationHeader' => 'Caixa fechado',
                'notificationSubheader' => sprintf(
                    '%s concluiu o fechamento de caixa.',
                    trim((string) ($device->getAlias() ?: $device->getDevice()))
                ),
                'notificationStatusLabel' => 'Fechado',
                'message' => 'Fechamento de caixa concluido.',
                'sentAt' => date(DATE_ATOM),
                'alertSound' => true,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '{}',
            $provider,
            $this->resolveCurrentUser()
        );
    }

    public function notify(Device $device, People $provider)
    {
        $numbers = $this->configService->getConfig($provider, 'cash-register-notifications', true);

        $filters = [
            'device.device' => $device->getDevice(),
            'receiver' => $provider->getId()
        ];
        $paymentData = $this->inFlowService->getPayments($filters);

        $connection = $this->whatsAppService->searchConnectionFromPeople($provider, 'support', true);
        if (!$connection) return;

        $phone = $connection->getPhone();
        $origin = $phone->getDdi() . $phone->getDdd() . $phone->getPhone();

        foreach ($numbers as $number) {
            $message = json_encode([
                "action" => "sendMessage",
                "origin" => $origin,
                "destination" => $number,
                "message" => $this->generateFormattedMessage($this->generateData($device, $provider), $paymentData)
            ]);
            $this->integrationService->addIntegration($message, 'WhatsApp', $device, null, $provider);
        }
    }

    public function generateFormattedMessage(array $products, array $paymentData): string
    {
        $message = "*📦 FECHAMENTO DE CAIXA*\n";
        $message .= date('d/m/Y H:i') . "\n\n";

        $totalGeral = 0;
        $message .= "*Produtos vendidos:*\n";

        foreach ($products as $product) {
            $nome = $product['product_name'];
            $desc = $product['product_description'];
            $qtd = $product['quantity'];
            $total = number_format($product['order_product_total'], 2, ',', '.');
            $message .= "- {$qtd}x {$nome}" . ($desc ? " ({$desc})" : "") . ": R$ {$total}\n";
            $totalGeral += $product['order_product_total'];
        }

        $message .= "\n*Total em produtos:* R$ " . number_format($totalGeral, 2, ',', '.') . "\n\n";

        $message .= "*Pagamentos:*\n";
        $pagamentoTotal = 0;

        foreach ($paymentData['wallet'] as $wallet) {
            $message .= strtoupper($wallet['wallet']) . ":\n";

            foreach ($wallet['payment'] as $payment) {
                $tipo = $payment['payment'];
                $valor = number_format($payment['inflow'], 2, ',', '.');
                $message .= "- {$tipo}: R$ {$valor}\n";
                $pagamentoTotal += $payment['inflow'];
            }

            $message .= "Subtotal: R$ " . number_format($wallet['total'], 2, ',', '.') . "\n\n";
        }

        $message .= "*Total recebido:* R$ " . number_format($pagamentoTotal, 2, ',', '.');

        return $message;
    }


    public function open(Device $device, People $provider)
    {
        // O primeiro ciclo do caixa pode nao ter invoice anterior para servir de marco inicial.
        $this->deviceService->addDeviceConfigs($provider, [
            'cash-wallet-open-id' => $this->resolveLastInvoiceId($device, $provider),
            'cash-wallet-closed-id' => 0,
        ], $device->getDevice(), $this->pdvDeviceType);

        $openedAt = new \DateTimeImmutable('now');
        $operatorLabel = $this->resolveCurrentUserLabel();
        $openedAtLabel = $openedAt->format('d/m/Y H:i');
        $deviceLabel = trim((string) ($device->getAlias() ?: $device->getDevice()));

        $this->integrationService->addManagerPushIntegrations(
            json_encode(
                [
                    'store' => 'orders',
                    'event' => 'cash.open',
                    'company' => (string) $provider->getId(),
                    'provider' => (string) $provider->getId(),
                    'providerName' => trim((string) ($provider->getName() ?: $provider->getAlias() ?: 'Loja')),
                    'device' => $device->getDevice(),
                    'deviceId' => (string) $device->getId(),
                    'deviceAlias' => $deviceLabel,
                    'operator' => $operatorLabel,
                    'openedAt' => $openedAt->format(DATE_ATOM),
                    'openedAtLabel' => $openedAtLabel,
                    'notificationHeader' => 'Caixa aberto',
                    'notificationSubheader' => sprintf(
                        '%s abriu o caixa às %s.',
                        $operatorLabel,
                        $openedAtLabel
                    ),
                    'notificationStatusLabel' => 'Aberto',
                    'message' => sprintf(
                        'Caixa %s aberto por %s às %s.',
                        $deviceLabel ?: $device->getDevice(),
                        $operatorLabel,
                        $openedAtLabel
                    ),
                    'sentAt' => $openedAt->format(DATE_ATOM),
                    'alertSound' => true,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '{}',
            $provider,
            $this->resolveCurrentUser()
        );
    }

    private function resolveCurrentUser(): ?User
    {
        $user = $this->security?->getToken()?->getUser();

        return $user instanceof User ? $user : null;
    }

    private function resolveCurrentUserLabel(): string
    {
        $user = $this->resolveCurrentUser();
        if (!$user instanceof User) {
            return 'Usuario autenticado';
        }

        $people = $user->getPeople();
        $label = trim((string) ($people->getName() ?: $people->getAlias() ?: ''));
        if ($label !== '') {
            return $label;
        }

        return trim((string) $user->getUsername()) ?: 'Usuario autenticado';
    }

    private function resolveLastInvoiceId(Device $device, People $provider): int
    {
        $lastInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(
            [
                'receiver' => $provider,
                'device' => $device
            ],
            ['id' => 'DESC']
        );

        return $lastInvoice?->getId() ?? 0;
    }



    public function generateData(Device $device, People $provider)
    {

        $deviceConfig = $this->deviceService
            ->findDeviceConfig($device, $provider, $this->pdvDeviceType)
            ?->getConfigs(true) ?? [];

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('product_name', 'product_name');
        $rsm->addScalarResult('product_description', 'product_description');
        $rsm->addScalarResult('product_sku', 'product_sku');
        $rsm->addScalarResult('quantity', 'quantity', Types::FLOAT);
        $rsm->addScalarResult('order_product_price', 'order_product_price', Types::FLOAT);
        $rsm->addScalarResult('order_product_total', 'order_product_total', Types::FLOAT);

        $sql = '
            SELECT 
                p.product AS product_name,
                p.description AS product_description,
                p.sku AS product_sku,
                SUM(op.quantity) AS quantity,
                op.price AS order_product_price,
                SUM(op.total) AS order_product_total
            FROM order_product op 
            INNER JOIN product p ON op.product_id = p.id
            WHERE op.order_id IN
            (';
        $sql .= $this->inFlowService->getSubquery($deviceConfig);
        $sql .= ') AND p.type IN (:type)
            GROUP BY p.id, p.product, p.description, p.sku
            ORDER BY p.product ASC
        ';

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query
            ->setParameter('type', ['product', 'custom', 'manufactured'])
            ->setParameter('device', $device->getDevice())
            ->setParameter('provider', $provider->getId());

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id']) && $deviceConfig['cash-wallet-open-id'] > 0)
            $query->setParameter('minId', $deviceConfig['cash-wallet-open-id']);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0)
            $query->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);

        return $query->getArrayResult();
    }

    public function generatePrintData(Device $device, People $provider): Spool
    {
        $products = $this->generateData($device, $provider);

        $filters = [
            'device.device' => $device->getDevice(),
            'receiver' => $provider->getId()
        ];
        $paymentData = $this->inFlowService->getPayments($filters);

        $this->printService->addLine("RELATÓRIO DE CAIXA");
        $this->printService->addLine(date('d/m/Y H:i'));
        $this->printService->addLine($provider->getName());
        $this->printService->addLine("", "", "-");

        foreach ($paymentData['wallet'] as $walletId => $wallet) {
            $this->printService->addLine(strtoupper($wallet['wallet']) . ":");

            foreach ($wallet['payment'] as $payment) {
                if ($payment['inflow'] > 0) {
                    $this->printService->addLine(
                        "  " . $payment['payment'],
                        "R$ " . number_format($payment['inflow'], 2, ',', '.'),
                        "."
                    );
                }
                if ($payment['withdrawal'] > 0) {
                    $this->printService->addLine(
                        "  Sangria " . $wallet['withdrawal-wallet'],
                        "R$ " . number_format($payment['withdrawal'], 2, ',', '.'),
                        "."
                    );
                }
            }

            $this->printService->addLine(
                "  Total",
                "R$ " . number_format($wallet['total'], 2, ',', '.'),
                "."
            );
            $this->printService->addLine("", "", "-");
        }

        $total = 0;
        $this->printService->addLine("PRODUTOS:");
        foreach ($products as $product) {
            $quantity = $product['quantity'];
            $productName = substr($product['product_name'], 0, 20);
            $subtotal = $product['order_product_total'];
            $total += $subtotal;

            $this->printService->addLine(
                "  $quantity X " . $productName,
                "R$ " . number_format($subtotal, 2, ',', '.'),
                "."
            );
        }

        $this->printService->addLine("", "", "-");
        $this->printService->addLine(
            "TOTAL",
            "R$ " . number_format($total, 2, ',', '.'),
            " "
        );
        $this->printService->addLine("", "", "-");

        return $this->printService->generatePrintData($device, $provider, [
            'type' => $this->pdvDeviceType,
        ]);
    }

    private function normalizeReference(mixed $reference): mixed
    {
        if (!is_string($reference)) {
            return $reference;
        }

        $reference = trim($reference);

        return $reference === '' ? null : $reference;
    }

    private function requireResolvedContext(?Device $device, ?People $provider): void
    {
        if (!$device && !$provider) {
            throw new InvalidArgumentException('Device and provider are required.');
        }

        if (!$device) {
            throw new InvalidArgumentException('Device is required.');
        }

        if (!$provider) {
            throw new InvalidArgumentException('Provider is required.');
        }
    }

    public function resolveDeviceAndProvider(
        mixed $deviceReference,
        mixed $providerReference
    ): array {
        $providerReference = $this->normalizeReference($providerReference);
        $deviceReference = $this->normalizeReference($deviceReference);

        $provider = $providerReference === null
            ? null
            : $this->entityManager->getRepository(People::class)->find($providerReference);
        $device = $deviceReference === null
            ? null
            : $this->entityManager->getRepository(Device::class)->findOneBy([
                'device' => (string) $deviceReference,
            ]);

        return [$device, $provider];
    }

    public function notifyFromReferences(mixed $deviceReference, mixed $providerReference): void
    {
        [$device, $provider] = $this->resolveDeviceAndProvider($deviceReference, $providerReference);
        $this->requireResolvedContext($device, $provider);
        $this->notify($device, $provider);
    }

    public function closeFromReferences(mixed $deviceReference, mixed $providerReference)
    {
        [$device, $provider] = $this->resolveDeviceAndProvider($deviceReference, $providerReference);
        $this->requireResolvedContext($device, $provider);

        return $this->close($device, $provider);
    }

    public function openFromReferences(mixed $deviceReference, mixed $providerReference)
    {
        [$device, $provider] = $this->resolveDeviceAndProvider($deviceReference, $providerReference);
        $this->requireResolvedContext($device, $provider);

        return $this->open($device, $provider);
    }

    public function generateDataFromReferences(mixed $deviceReference, mixed $providerReference)
    {
        [$device, $provider] = $this->resolveDeviceAndProvider($deviceReference, $providerReference);

        if (!$device || !$provider) {
            return [];
        }

        return $this->generateData($device, $provider);
    }

    public function generatePrintDataFromContent(?string $content): Spool
    {
        $payload = $this->requestPayloadService->decodeJsonContent($content);
        [$device, $provider] = $this->resolveDeviceAndProvider(
            $payload['device'] ?? '',
            $payload['people'] ?? null
        );
        $this->requireResolvedContext($device, $provider);

        return $this->generatePrintData($device, $provider);
    }

    public function notifyFromContent(?string $content): void
    {
        $payload = $this->requestPayloadService->decodeJsonContent($content);

        $this->notifyFromReferences(
            $payload['device'] ?? '',
            $payload['people'] ?? null
        );
    }
}
