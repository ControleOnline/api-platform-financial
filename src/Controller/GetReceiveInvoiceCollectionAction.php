<?php

namespace App\Controller;

use App\Entity\People;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetReceiveInvoiceCollectionAction
{
    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;

    /**
     * Request
     *
     * @var Request
     */
    private $request  = null;

    /**
     * Security
     *
     * @var Security
     */
    private $security = null;

    /**
     * @var \ControleOnline\Repository\ReceiveInvoiceRepository
     */
    private $repository = null;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->manager    = $entityManager;
        $this->security   = $security;
        $this->repository = $this->manager->getRepository(\ControleOnline\Entity\ReceiveInvoice::class);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->request = $request;

        $results = $this->getResults($this->getFilters());

        return new JsonResponse([
            "@context"         => "/contexts/ReceiveInvoice",
            "@id"              => "/finance/receive",
            "@type"            => "hydra:Collection",
            "hydra:member"     => $results['members'],
            "hydra:totalItems" => $results['totalRows'],
        ]);
    }

    private function getFilters(): array
    {
        $myCompany  = $this->getMyCompany();

        $searchBy = $this->request->query->get('searchBy', null);

        if (preg_match('/[a-z();:|!"#$%&\/=?~^><ªº\\s-]/i', $searchBy)) {
            $numeric = preg_replace('/[.(),;:|!"#$%&\/=?~^><ªº\\s-]/', '', $searchBy);

            if (!is_numeric($numeric)) {
                $searchBy = preg_replace('/[.(),;:|!"#$%&\/=?~^><ªº\\s-]/', '%', $searchBy);
            } else {
                $searchBy = $numeric;
            }
        } else {
            if (preg_match("/[,]/i", $searchBy)) {
                $searchBy = preg_replace('/[,]/', '.', $searchBy);
            }
        }

        $status     = $this->request->query->get('status', null);
        $realStatus = $this->request->query->get('status_realStatus', null);
        $orderId    = $this->request->query->get('order_order', null);


        $from    = $this->request->query->get('from', null);
        $to    = $this->request->query->get('to', null);

        $orderType = $this->request->query->get('orderType', null);

        return [
            'providerId' => $myCompany ? $myCompany->getId() : -1,
            'searchBy'   => $searchBy,
            'status'     => $status,
            'realStatus' => $realStatus,
            'orderId'    => $orderId,
            'dateFrom'   => $from,
            'dateTo'     => $to,
            'orderType'  => $orderType,

        ];
    }

    private function getResults(array $filters): array
    {
        $maxItems = $this->request->query->get('itemsPerPage', 10);
        $pageNum  = $this->request->query->get('page', 1);

        $totalRows = $this->repository->getInvoiceCollectionCount($filters);
        $fromRow   = $maxItems * ($pageNum - 1);

        $members   = [];
        $invoices  = $this->repository->getInvoiceCollection($filters, $fromRow, $maxItems);
        foreach ($invoices as $invoice) {
            $members[] = $this->getInvoice($invoice);
        }

        return [
            'members'   => $members,
            'totalRows' => $totalRows
        ];
    }

    private function getInvoice(array $invoice): array
    {
        $orders = [];

        foreach ($invoice['orders'] as $order) {
            $orders[] = [
                "order" => [
                    "@id"     => sprintf("/sales/orders/%d", $order['id']),
                    "@type"   => $order['order_type'],
                    "client"  => [
                        "@id"   => sprintf("/people/%d", $order['client_id']),
                        "@type" => "People",
                        "name"  => $order['client_name'],
                        "alias" => $order['client_alias']
                    ],
                    "payer"  => [
                        "@id"   => sprintf("/people/%d", $order['payer_id']),
                        "@type" => "People",
                        "name"  => $order['payer_name'],
                        "alias" => $order['payer_alias']
                    ],

                    "provider" => [
                        "@id"   => sprintf("/people/%d", $order['provider_id']),
                        "@type" => "People",
                        "name"  => $order['provider_name'],
                        "alias" => $order['provider_alias']
                    ]
                ]
            ];
        }

        return [
            "@id"           => sprintf("/finance/receive/%d", $invoice['id']),
            "@type"         => "ReceiveInvoice",
            "invoice_type"  => $invoice['invoice_type'],
            "order"         => $orders,
            "status" => [
                "@id"        => sprintf("/statuses/%d", $invoice['status_id']),
                "@type"      => "Status",
                "status"     => $invoice['status_status'],
                "realStatus" => $invoice['status_real_status'],
                "color"      => $invoice['status_color']
            ],
            "dueDate"       => $invoice['due_date'],
            "price"         => $invoice['price']
        ];
    }

    private function getMyCompany(): ?People
    {
        /**
         * @var \ControleOnline\Entity\User $currentUser
         */
        $currentUser  = $this->security->getUser();
        $clientPeople = $this->request->query->get('myCompany', null);

        if ($clientPeople === null) {
            $companies = $currentUser->getPeople() ? $currentUser->getPeople()->getPeopleCompany() : null;

            if (empty($companies) || $companies->first() === false) {
                return $currentUser->getPeople();
            }

            return $companies->first()->getCompany();
        }

        $clientPeople = $this->manager->find(People::class, $this->request->query->get('myCompany'));

        if ($clientPeople instanceof People) {

            // verify if client is a company of current user

            $isMyCompany = $currentUser->getPeople()->getPeopleCompany()->exists(
                function ($key, $element) use ($clientPeople) {
                    return $element->getCompany() === $clientPeople;
                }
            );

            if ($isMyCompany === false) {
                return null;
            }
        }

        return $clientPeople;
    }
}
