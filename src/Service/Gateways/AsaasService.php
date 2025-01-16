<?php

namespace ControleOnline\Service\Gateways;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use ControleOnline\Service\PeopleRoleService;
use GuzzleHttp\Client;

class AsaasService
{
    private $entryPoint = '	https://api.asaas.com/v3';
    private $client;
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleRoleService $peopleRoleService
    ) {}

    private function getApiKey(Invoice $invoice)
    {
        $receiver = $invoice->getReceiver();
        $asaasKey = $this->manager->getRepository(Config::class)->findOneBy([
            'company' => $receiver,
            'key' => 'asaas-key'
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
    }


    public function getPix(Invoice $invoice)
    {
        $this->init($invoice);
        $receiver = $invoice->getReceiver();
        $pixKey = $this->manager->getRepository(Config::class)->findOneBy([
            'company' => $receiver,
            'key' => 'asaas-receiver-pix-key'
        ]);

        if (!$pixKey) throw new \Exception('Pix key not found');

        $response = $this->client->request('POST',  '/pix/qrCodes/static', [
            'json' => [
                "addressKey" => $pixKey->getConfigValue(),
                "value" => $invoice->getPrice(),
                "allowsMultiplePayments" => false,
                "externalReference" => $invoice->getId()
            ]
        ]);

        $response->getBody();
    }
}
