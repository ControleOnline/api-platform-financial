<?php

namespace ControleOnline\Controller;

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


    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = $this->asaasService->returnWebhook($request);
            return new JsonResponse($this->hydratorService->result($result));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
