<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardController 
{
    public function __construct(
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Card $card,): JsonResponse
    {
        try {
            return new JsonResponse($this->hydratorService->item(Card::class, $card->getId(), 'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
