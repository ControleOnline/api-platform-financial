<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetCashRegisterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CashRegisterService $cashRegister
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $deviceId = $request->query->get('device');
        $companyId = $request->query->get('company');

        $device = $this->entityManager->getRepository(Device::class)->find($deviceId);
        $company = $this->entityManager->getRepository(People::class)->find($companyId);

        $data = $this->cashRegister->generateData($device, $company);

        return new JsonResponse($data);
    }
}
