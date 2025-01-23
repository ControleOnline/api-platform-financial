<?php

namespace ControleOnline\Controller;

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


    public function __invoke(Request $request, $people_id): JsonResponse
    {
        try {

            $json =       json_decode($request->getContent(), true);
            $people = $this->manager->getRepository(People::class)->find($people_id);
            $result = $this->asaasService->returnWebhook($people, $json);

            return new JsonResponse($this->hydratorService->data($result, ['groups' => 'invoice:read']));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
