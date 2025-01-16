<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Document;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Status;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class PaylistController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        private HydratorService $hydratorService

    ) {}

    /**
     * @Route("/paylist", name="paylist", methods={"GET"})
     */

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $document = $request->get('document', null);
            $receiver = $request->get('company', null);


            $status = $this->manager->getRepository(Status::class)->findBy([
                'realStatus' => 'pending',
                'context' => 'invoice',
            ]);
            $people_document = $this->manager->getRepository(Document::class)->findOneBy([
                'document' => $document,
            ]);
            if (!$people_document)
                throw new Exception('Document not found');

            $result = $this->manager->getRepository(Invoice::class)->findBy([
                'receiver' => $receiver,
                'payer' => $people_document->getPeople(),
                'status' => $status,
            ]);

            return new JsonResponse($this->hydratorService->collectionData($result, Invoice::class, 'invoice:read'));
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
