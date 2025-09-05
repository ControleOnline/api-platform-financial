<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Card;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use ControleOnline\Service\HydratorService;
use ControleOnline\Service\CardService;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\Security;

class CardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CardService $cardService,        
        private HydratorService $hydratorService
    ) {}

    #[Route('/people/cards', name: 'cards', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function cards(Request $request): JsonResponse
    {
        try {            
            $cardResume = $this->cardService->findCardResumeByPeople();                        
            return new JsonResponse($this->hydratorService->collectionData($cardResume, Card::class, 'card:read'));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/people/cards/{id}', name: 'get_card', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getCard(int $id): JsonResponse
    {
        try {
            $card = $this->cardService->findCardById($id);
            if (!$card) {
                return new JsonResponse(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
            }
            
            return new JsonResponse($this->hydratorService->collectionData($card, Card::class, 'card:read'));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/people/cards', name: 'create_card', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function createCard(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);    
            $savedCard = $this->cardService->saveCard($this->cardService->toObject($data));            

            return new JsonResponse($this->hydratorService->collectionData($savedCard, Card::class, 'card:read'));
            
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/people/cards/{id}', name: 'update_card', methods: ['PUT'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function updateCard(int $id, Request $request): JsonResponse
    {
        try {
            $card = $this->cardService->findCardById($id);
            if (!$card) {
                return new JsonResponse(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);           
            $updatedCard = $this->cardService->saveCard($this->cardService->toObject($data));
            
            return new JsonResponse($this->hydratorService->collectionData($updatedCard, Card::class, 'card:read'));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/people/cards/{id}', name: 'delete_card', methods: ['DELETE'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function deleteCard(int $id): JsonResponse
    {
        try {
            $result = $this->cardService->deleteCard($id);
            if (!$result) {
                return new JsonResponse(['error' => 'Card not found or could not be deleted'], Response::HTTP_NOT_FOUND);
            }
            
            return new JsonResponse(['message' => 'Card deleted successfully'], Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}