<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use ControleOnline\Service\BitcoinService;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class BitcoinController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        private HydratorService $hydratorService,
        private BitcoinService $bitcoinService
    ) {}


    public function __invoke(Request $request): JsonResponse
    {
        try {
            $json =       json_decode($request->getContent(), true);
            $invoiceId = $json['invoice'] ?? null;
            if (!$invoiceId)
                throw new Exception('Invoice not found');

            $invoice = $this->manager->getRepository(Invoice::class)->find($invoiceId);
            if (!$invoice)
                throw new Exception('Invoice not found');

            $result = $this->bitcoinService->getBitcoin($invoice);

            return new JsonResponse($this->hydratorService->result($result));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
