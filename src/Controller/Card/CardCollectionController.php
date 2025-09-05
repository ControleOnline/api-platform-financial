<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardCollectionController extends AbstractController
{
    public function __construct(
        private CardService $cardService,
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            die('e');
            $cardResume = $this->cardService->findCardResumeByPeople();
            return new JsonResponse($this->hydratorService->collectionData($cardResume, Card::class, 'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
