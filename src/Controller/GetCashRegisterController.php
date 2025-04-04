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
        $providerId = $request->query->get('provider');

        $provider = $this->entityManager->getRepository(People::class)->find($providerId);
        $device = $this->entityManager->getRepository(Device::class)->findOneBy([
            'device' =>  $deviceId,
        ]);
        $data = $this->cashRegister->generateData($device, $provider);

        return new JsonResponse($data);
    }
}
