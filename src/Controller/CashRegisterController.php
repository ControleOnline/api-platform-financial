<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use ControleOnline\Service\HydratorService;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Spool;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\Security;

class CashRegisterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CashRegisterService $cashRegister,
        private HydratorService $hydratorService
    ) {}


    #[Route('/cash-register/close', name: 'close-cash_register', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function closeCashRegister(Request $request): JsonResponse
    {
        try {
            $deviceId = $request->query->get('device');
            $providerId = $request->query->get('provider');

            $provider = $this->manager->getRepository(People::class)->find($providerId);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' =>  $deviceId,
            ]);
            $data = $this->cashRegister->close($device, $provider);
            $this->cashRegister->notify($device, $provider);
            return new JsonResponse($data);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
    #[Route('/cash-register/open', name: 'open-cash_register', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function openCashRegister(Request $request): JsonResponse
    {
        try {
            $deviceId = $request->query->get('device');
            $providerId = $request->query->get('provider');

            $provider = $this->manager->getRepository(People::class)->find($providerId);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' =>  $deviceId,
            ]);
            $data = $this->cashRegister->open($device, $provider);

            return new JsonResponse($data);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }




    #[Route('/cash-register', name: 'cash_register', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getCashRegister(Request $request): JsonResponse
    {
        try {
            $deviceId = $request->query->get('device');
            $providerId = $request->query->get('provider');

            $provider = $this->manager->getRepository(People::class)->find($providerId);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' =>  $deviceId,
            ]);
            $data = $this->cashRegister->generateData($device, $provider);

            return new JsonResponse($data);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/cash-register/print', name: 'print_cash_register', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function printCashRegister(Request $request): JsonResponse
    {
        try {

            $data = json_decode($request->getContent(), true);
            $company = $this->manager->getRepository(People::class)->find($data['people']);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' => $data['device']
            ]);
            $printData = $this->cashRegister->generatePrintData($device, $company);
            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/income_statements', name: 'invoice_inflow', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
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


    #[Route('/monthly_statements', name: 'monthly_statements', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getMonthlyStatements(Request $request): Response
    {
        try {
            $year = $request->get('year', null);
            $month = $request->get('month', null);
            $people = $request->get('people', null);
            $result = $this->manager->getRepository(Invoice::class)->getMonthlyDRE($this->manager->getRepository(People::class)->find($people), $year, $month);

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
