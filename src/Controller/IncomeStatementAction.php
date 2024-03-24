<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\HydratorService;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IncomeStatementAction
{
    public function __construct(
        private EntityManagerInterface $manager,
        private HydratorService $hydratorService
    ) {
    }

    public function __invoke(Request $request): Response
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
