<?php

namespace ControleOnline\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

use ControleOnline\Service\ConfigService;
use ControleOnline\Service\DeviceService;
use ControleOnline\Service\InFlowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 AS Security;

class InvoiceDataProvider implements ProviderInterface
{



    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ConfigService $configService,
        private InFlowService $inFlowService,
        private DeviceService $deviceService

    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getToken()->getUser();
        if (!$currentUser && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new \Exception('You should not pass!!!');
        }
        $filters = $context['filters'] ?? [];
        $payments = $this->inFlowService->getPayments($filters);
        return [[
            'payments' => $payments
        ]];
    }
}
