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
use ControleOnline\Service\HydratorService;
use ControleOnline\Entity\Invoice;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class GetCashRegisterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CashRegisterService $cashRegister,
        private HydratorService $hydratorService
    ) {}

    /**
     * @Route("/cash-register", name="invoice_inflow", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function getCashRegister(Request $request): JsonResponse
    {
        $deviceId = $request->query->get('device');
        $providerId = $request->query->get('provider');

        $provider = $this->manager->getRepository(People::class)->find($providerId);
        $device = $this->manager->getRepository(Device::class)->findOneBy([
            'device' =>  $deviceId,
        ]);
        $data = $this->cashRegister->generateData($device, $provider);

        return new JsonResponse($data);
    }

    /**
     * @Route("/cash-register/print", name="cash_register", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function printCashRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $printType = $data['print-type'];
        $deviceType = $data['device-type'];
        $company = $this->manager->getRepository(People::class)->find($data['people']);
        $device = $this->manager->getRepository(Device::class)->findOneBy([
            'device' => $data['device']
        ]);

        $printData = $this->cashRegister->generatePrintData($device, $company, $printType, $deviceType);

        return new JsonResponse($printData);
    }

    /**
     * @Route("/income_statements", name="cash_register", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function getIncomeStatements(Request $request): Response
    {
        try {
            $year = $request->get('year', null);
            $month = $request->get('month', null);
            $people = $request->get('people', null);
            $result = $this->manager->getRepository(Invoice::class)->getDRE($this->manager->getRepository(People::class)->find($people), $year, $month);

            return new JsonResponse($this->hydratorService->result($result));
        } catch (Exception $e) {
            return new JsonResponse([
                'response' => [
                    'data'    => [],
                    'count'   => 0,
                    'error'   => [
                        'message' => $e->getMessage(),
                        'line'   => $e->getLine(),
                        'file' => $e->getFile()
                    ],
                    'success' => false,
                ],
            ]);
        }
    }
}
