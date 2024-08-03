<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Service\BraspagService;
use Psr\Log\LoggerInterface;


class SplitInvoiceAction
{


    public function __construct(private EntityManagerInterface $manager, private BraspagService $braspag, private  LoggerInterface $logger)
    {
    }


    /**
     * @param Invoice $data
     * @param Request $request
     * @param string|null $operation
     * @return BinaryFileResponse|JsonResponse
     */
    public function __invoke(Invoice $data, Request $request)
    {

        try {
            $ret = $this->braspag->split($data);
            return new JsonResponse($ret, 200);
        } catch (\Exception $e) {
            $this->logger->error('Failed', ['exception' => $e]);
            return new JsonResponse([
                'response' => [
                    'data'    => [],
                    'count'   => 0,
                    'error'   => $e->getMessage(),
                    'success' => false,
                ],
            ], 500);
        }
    }
}
