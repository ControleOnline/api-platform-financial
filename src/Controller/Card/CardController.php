<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardController
{
    public function __construct(
        private HydratorService $hydratorService,
        private CardService $cardService
    ) {}

    public function __invoke(Card $card): JsonResponse
    {
        try {

            $cardResume = $this->cardService->findCardById($card->getId());
            return new JsonResponse($this->hydratorService->data($cardResume,  'card:read'));
            
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
