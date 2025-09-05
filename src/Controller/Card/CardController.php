<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardController extends AbstractController
{
    public function __construct(
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Card $card, ): JsonResponse
    {
        try {
            return new JsonResponse($this->hydratorService->collectionData($card, Card::class, 'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}