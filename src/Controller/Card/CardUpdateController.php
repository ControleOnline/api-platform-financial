<?php

namespace ControleOnline\Controller\Card;


use ControleOnline\Entity\Card;
use ControleOnline\Service\CardService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CardUpdateController 
{
    public function __construct(
        private CardService $cardService,
        private HydratorService $hydratorService
    ) {}

    public function __invoke(Card $card, Request $request, ): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['people_id'])) $card->setPeopleId($data['people_id']);
            if (isset($data['type'])) $card->setType($data['type']);
            if (isset($data['name'])) $card->setName($data['name']);
            if (isset($data['document'])) $card->setDocument($data['document']);
            if (isset($data['number_group_1'])) $card->setNumberGroup1($data['number_group_1']);
            if (isset($data['number_group_2'])) $card->setNumberGroup2($data['number_group_2']);
            if (isset($data['number_group_3'])) $card->setNumberGroup3($data['number_group_3']);
            if (isset($data['number_group_4'])) $card->setNumberGroup4($data['number_group_4']);
            if (isset($data['ccv'])) $card->setCcv($data['ccv']);
            if (isset($data['expiration_month'])) $card->setExpirationMonth($data['expiration_month']);
            if (isset($data['expiration_year'])) $card->setExpirationYear($data['expiration_year']);            

            $updatedCard = $this->cardService->saveCard($card);
                        
            
            return new JsonResponse($this->hydratorService->data($updatedCard,  'card:read'));
        } catch (\Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
