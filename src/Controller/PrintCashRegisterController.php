<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class PrintCashRegisterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CashRegisterService $print

    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $printType = $data['print-type'];
        $deviceType = $data['device-type'];
        $device = $this->entityManager->getRepository(Device::class)->find($data['device']);
        $company = $this->entityManager->getRepository(People::class)->find($data['company']);

        $printData = $this->print->generatePrintData($device, $company, $printType, $deviceType);

        return new JsonResponse($printData);
    }
}
