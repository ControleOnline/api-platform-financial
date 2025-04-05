<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class GetCashRegisterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CashRegisterService $cashRegister
    ) {}

    /**
     * @Route("/invoice/inflow", name="invoice_inflow", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function getInflow(Request $request): JsonResponse
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

    /**
     * @Route("/cash-register", name="cash_register", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function printCashRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $printType = $data['print-type'];
        $deviceType = $data['device-type'];
        $company = $this->entityManager->getRepository(People::class)->find($data['people']);
        $device = $this->entityManager->getRepository(Device::class)->findOneBy([
            'device' => $data['device']
        ]);

        $printData = $this->cashRegister->generatePrintData($device, $company, $printType, $deviceType);

        return new JsonResponse($printData);
    }
}
