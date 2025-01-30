<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use ControleOnline\Service\Gateways\AsaasService;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class AsaasWebhookController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        private HydratorService $hydratorService,
        private AsaasService $asaasService
    ) {}


    public function __invoke(Request $request, People $data): JsonResponse
    {
        try {

            $json =       json_decode($request->getContent(), true);
            $token = $request->headers->get('asaas-access-token');
  

            $invoice = $this->asaasService->returnWebhook($data, $json,$token);

            return new JsonResponse($this->hydratorService->item(Invoice::class, $invoice->getId(), ['groups' => 'invoice:read']));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
