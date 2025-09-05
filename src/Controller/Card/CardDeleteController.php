<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardDeleteController 
{
    public function __construct(
        private CardService $cardService,
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Card $card, ): JsonResponse
    {
        try {
            $result = $this->cardService->deleteCard($card->getId());
            if (!$result) {
                return new JsonResponse(['error' => 'Card could not be deleted'], Response::HTTP_NOT_FOUND);
            }
            return new JsonResponse(['message' => 'Card deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}