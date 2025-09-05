<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardCreateController 
{
    public function __construct(
        private CardService $cardService,
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Request $request,): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $savedCard = $this->cardService->saveCard($this->cardService->toObject($data));
            
            return new JsonResponse($this->hydratorService->item(Card::class, $savedCard->getId(), 'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
