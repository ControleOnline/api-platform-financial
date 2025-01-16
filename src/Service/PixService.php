<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\Invoice;
use ControleOnline\Service\Gateways\AsaasService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use ControleOnline\Service\PeopleRoleService;
use GuzzleHttp\Client;

class PixService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private AsaasService $asaasService,

    ) {}

    public function getPix(Invoice $invoice)
    {
        return $this->asaasService->getPix($invoice);
    }
}
