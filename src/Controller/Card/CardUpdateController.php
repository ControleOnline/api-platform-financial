<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CardUpdateController 
{
    public function __construct(
        private CardService $cardService,
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Card $card, Request $request, ): JsonResponse
    {
        try {
            $updatedCard = $this->cardService->updateCardFromContent(
                $card,
                $request->getContent()
            );

            return new JsonResponse($this->hydratorService->data($updatedCard,  'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
