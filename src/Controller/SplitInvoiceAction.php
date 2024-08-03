<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ControleOnline\Service\BraspagService;

class SplitInvoiceAction
{


    public function __construct(private EntityManagerInterface $manager, private BraspagService $braspag)
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
        $ret = $this->braspag->split($data);
        return new JsonResponse($ret, 200);
    }
}
