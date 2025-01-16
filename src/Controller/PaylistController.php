<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class PaylistController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        private HydratorService $hydratorService

    ) {}

    /**
     * @Route("/paylist", name="paylist", methods={"GET"})
     */

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $document = $request->get('document', null);
            $company = $request->get('company', null);
            $result = $this->manager->getRepository(Invoice::class)->findBy([
                'company' => $company,
                'document' => $document,
            ]);

            return new JsonResponse($this->hydratorService->result([]));
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
