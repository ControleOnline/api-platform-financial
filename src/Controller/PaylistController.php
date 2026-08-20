<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Document;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Status;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public paylist by document (CPF/CNPJ) and optional receiver company.
 * Security: PUBLIC_ACCESS on /paylist (Invoice ApiResource).
 * Returns only open/pending/overdue invoices for that payer+receiver pair.
 */
class PaylistController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $manager,
        private HydratorService $hydratorService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $rawDocument = $request->get('document', null);
            $receiver = $request->get('company', null);

            if (!$rawDocument) {
                throw new Exception('Document not found');
            }

            $document = preg_replace('/\D+/', '', (string) $rawDocument);
            if ($document === '') {
                throw new Exception('Document not found');
            }

            $status = $this->manager->getRepository(Status::class)->findBy([
                'realStatus' => ['pending', 'open', 'overdue'],
                'context' => 'invoice',
            ]);

            $peopleDocument = $this->manager->getRepository(Document::class)->findOneBy([
                'document' => $document,
            ]);

            if (!$peopleDocument && $document !== (string) $rawDocument) {
                $peopleDocument = $this->manager->getRepository(Document::class)->findOneBy([
                    'document' => (string) $rawDocument,
                ]);
            }

            if (!$peopleDocument) {
                throw new Exception('Document not found');
            }

            $criteria = [
                'payer' => $peopleDocument->getPeople(),
                'status' => $status,
            ];
            if ($receiver) {
                $criteria['receiver'] = $receiver;
            }

            $result = $this->manager->getRepository(Invoice::class)->findBy($criteria);

            return new JsonResponse(
                $this->hydratorService->collectionData($result, Invoice::class, 'invoice:read')
            );
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }
}
