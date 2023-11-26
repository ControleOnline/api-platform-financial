<?php


namespace ControleOnline\Controller;

use ControleOnline\Entity\PayInvoice;
use ControleOnline\Entity\PurchasingOrder AS Order;
use App\Entity\People;
use ControleOnline\Entity\SchoolClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class GetMyBillsAction
 * @package ControleOnline\Controller
 * @Route("/invoice")
 */
class GetMyBillsAction extends AbstractController
{
    public $em;

    /**
     * @param Request $request
     * @return JsonResponse
     * @Route("/my")
     */
    public function getMyInvoices(Request $request): JsonResponse
    {
        try {
            $peopleId = $request->query->get('people_id');
            if (!is_numeric($peopleId) || $peopleId <= 0) {
                return $this->json([
                    'response' => [
                        'data' => [],
                        'count' => 0,
                        'error' => 'Invalid people id',
                        'success' => false,
                    ],
                ]);
            }

            $this->em = $this->getDoctrine()->getManager();

            $invoices = $this->getDoctrine()
                ->getRepository(PayInvoice::class)
                ->findInvoicesByPeople($peopleId);

            return $this->json([
                'response' => [
                    'data' => $invoices,
                    'count' => count($invoices),
                    'error' => '',
                    'success' => true,
                ],
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->json($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @Route("/orders")
     */
    public function getOrders(Request $request): JsonResponse
    {
        try {
            $invoiceId = $request->query->get('invoice_id');
            if (!is_numeric($invoiceId) || $invoiceId <= 0) {
                return $this->json([
                    'response' => [
                        'data' => [],
                        'count' => 0,
                        'error' => 'Invalid invoice id',
                        'success' => false,
                    ],
                ]);
            }

            $this->em = $this->getDoctrine()->getManager();
            $orders = $this->getDoctrine()
                ->getRepository(Order::class)
                ->findOrdersByInvoice($invoiceId);

            return $this->json([
                'response' => [
                    'data' => $orders,
                    'count' => count($orders),
                    'error' => '',
                    'success' => true,
                ],
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->json($e->getMessage());
        }
    }
}
