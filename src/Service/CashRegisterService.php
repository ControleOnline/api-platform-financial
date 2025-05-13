<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Spool;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\DBAL\Types\Types;

class CashRegisterService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrintService $printService,
        private ConfigService $configService,
        private InFlowService $inFlowService,
        private DeviceService $deviceService,
        private IntegrationService $integrationService
    ) {}

    public function close(Device $device, People $provider)
    {
        $lastInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(
            [
                'receiver' => $provider,
                'device' => $device
            ],
            ['id' => 'DESC']
        );

        $this->deviceService->addDeviceConfigs($provider, [
            'cash-wallet-closed-id' => $lastInvoice->getId(),
        ], $device->getDevice());

        $this->notify($device,  $provider);
    }

    public function notify(Device $device, People $provider)
    {
        $numbers = $this->configService->getConfig($provider, 'cash-register-notifications', true);

        $filters = [
            'device.device' => $device->getDevice(),
            'receiver' => $provider->getId()
        ];
        $paymentData = $this->inFlowService->getPayments($filters);

        foreach ($numbers as $number) {
            $message = json_encode([
                "action" => "sendMessage",
                "origin" => "551131360353",
                "message" => [
                    "number" => $number,
                    "message" => $this->generateFormattedMessage($this->generateData($device, $provider), $paymentData)
                ]
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
        $lastInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy([
            'receiver' => $provider,
            'device' => $device
        ]);

        $this->deviceService->addDeviceConfigs($provider, [
            'cash-wallet-open-id' => $lastInvoice->getId(),
            'cash-wallet-closed-id' => 0,
        ], $device->getDevice());
    }



    public function generateData(Device $device, People $provider)
    {

        $deviceConfig = $this->deviceService->discoveryDeviceConfig($device, $provider)->getConfigs(true);

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

        return $this->printService->generatePrintData($device, $provider);
    }
}
