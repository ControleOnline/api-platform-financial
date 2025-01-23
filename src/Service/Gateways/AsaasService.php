<?php

namespace ControleOnline\Service\Gateways;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\Invoice;
use ControleOnline\Service\DomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use ControleOnline\Service\PeopleRoleService;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AsaasService
{
    private $entryPoint = 'https://api.asaas.com/v3/';
    private $client;
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleRoleService $peopleRoleService,
        private DomainService $domainService
    ) {}

    private function getApiKey(Invoice $invoice)
    {
        $receiver = $invoice->getReceiver();
        $asaasKey = $this->manager->getRepository(Config::class)->findOneBy([
            'people' => $receiver,
            'config_key' => 'asaas-key'
        ]);

        if (!$asaasKey) throw new \Exception('Asaas key not found');

        return $asaasKey->getConfigValue();
    }

    private function init(Invoice $invoice)
    {
        if ($this->client)
            return $this->client;

        $this->client = new Client([
            'base_uri' => $this->entryPoint,
            'headers' => [
                'Accept' => 'application/json',
                'access_token' => $this->getApiKey($invoice),
                'Content-Type' => 'application/json',
            ]
        ]);

        $this->discoveryWebhook();
    }

    public function discoveryWebhook()
    {
        $response = $this->client->request('GET', 'webhooks');
        $webhook =  json_decode($response->getBody()->getContents(), true);
        $url = "https://" . $this->domainService->getMainDomain() . "/webhook/invoice/return";

        if ($webhook['totalCount'] != 0 || $webhook['data'][0]['url'] == $url)
            return;

        $response = $this->client->request('POST', 'webhooks', [
            'json' => [
                "name" => "Controle Online",
                "url" => $url,
                "email" => "luiz.kim@controleonline.com",
                "enabled" => true,
                "interrupted" => false,
                "sendType" => "NON_SEQUENTIALLY",
                "events" => [
                    'PAYMENT_CREATED',
                    'PAYMENT_AWAITING_RISK_ANALYSIS',
                    'PAYMENT_APPROVED_BY_RISK_ANALYSIS',
                    'PAYMENT_REPROVED_BY_RISK_ANALYSIS',
                    'PAYMENT_AUTHORIZED',
                    'PAYMENT_UPDATED',
                    'PAYMENT_CONFIRMED',
                    'PAYMENT_RECEIVED',
                    'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED',
                    'PAYMENT_ANTICIPATED',
                    'PAYMENT_OVERDUE',
                    'PAYMENT_DELETED',
                    'PAYMENT_RESTORED',
                    'PAYMENT_REFUNDED',
                    'PAYMENT_PARTIALLY_REFUNDED',
                    'PAYMENT_REFUND_IN_PROGRESS',
                    'PAYMENT_RECEIVED_IN_CASH_UNDONE',
                    'PAYMENT_CHARGEBACK_REQUESTED',
                    'PAYMENT_CHARGEBACK_DISPUTE',
                    'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
                    'PAYMENT_DUNNING_RECEIVED',
                    'PAYMENT_DUNNING_REQUESTED',
                    'PAYMENT_BANK_SLIP_VIEWED',
                    'PAYMENT_CHECKOUT_VIEWED',
                    'PAYMENT_SPLIT_CANCELLED',
                    'PAYMENT_SPLIT_DIVERGENCE_BLOCK',
                    'PAYMENT_SPLIT_DIVERGENCE_BLOCK_FINISHED',
                    'TRANSFER_CREATED',
                    'TRANSFER_PENDING',
                    'TRANSFER_IN_BANK_PROCESSING',
                    'TRANSFER_BLOCKED',
                    'TRANSFER_DONE',
                    'TRANSFER_FAILED',
                    'TRANSFER_CANCELLED',
                    'BILL_CREATED',
                    'BILL_PENDING',
                    'BILL_BANK_PROCESSING',
                    'BILL_PAID',
                    'BILL_CANCELLED',
                    'BILL_FAILED',
                    'BILL_REFUNDED'
                ]
            ],

        ]);
    }
    public function returnWebhook(Request $request) {}

    public function getPix(Invoice $invoice)
    {
        $this->init($invoice);
        $receiver = $invoice->getReceiver();
        $pixKey = $this->manager->getRepository(Config::class)->findOneBy([
            'people' => $receiver,
            'config_key'  => 'asaas-receiver-pix-key'
        ]);

        if (!$pixKey) throw new \Exception('Pix key not found');

        $response = $this->client->request('POST',  'pix/qrCodes/static', [
            'json' => [
                "addressKey" => $pixKey->getConfigValue(),
                "value" => $invoice->getPrice(),
                "allowsMultiplePayments" => false,
                "externalReference" => $invoice->getId()
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
