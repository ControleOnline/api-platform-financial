<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use App\Entity\Config;
use App\Entity\Invoice;
use App\Entity\Status;
use App\Entity\People;
use App\Entity\SalesOrder;
use App\Library\Itau\ItauClient;

class GetBankItauDataAction
{
    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $manager = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->manager = $entityManager;
    }

    public function __invoke(Invoice $data, Request $request, string $operation): JsonResponse
    {
        $handler = sprintf('get%s', ucfirst($operation));

        return new JsonResponse([
            'response' => [
                'data'    => $this->$handler($data),
                'count'   => 1,
                'error'   => '',
                'success' => true,
            ],
        ]);
    }

    /**
     * Retrieve ITAU hash
     *
     * @param  \ControleOnline\Entity\ReceiveInvoice $invoice
     * @return string
     */
    private function getItauhash(Invoice $invoice): string
    {
        if ($invoice->getOrder()->isEmpty())
            throw new ItemNotFoundException('Invoice orders not found');

        $order           = $invoice->getOrder()->first()->getOrder();

        $configs         = $this->getItauConfig($order->getProvider());

        $codEmp          = $configs['itau-shopline-company'];
        $chave           = $configs['itau-shopline-key'];

        $valor           = number_format($invoice->getPrice(), 2, ',', '');
        $nomeSacado      = $order->getPayer()->getName();
        $codigoInscricao = $order->getPayer()->getPeopleType() == 'J' ? '02' : '01';

        foreach ($order->getPayer()->getDocument() as $document) {
            if ($document->getDocumentType()->getDocumentType() == ($order->getPayer()->getPeopleType() == 'J' ? 'CNPJ' : 'CPF')) {
                $numeroInscricao = $document->getDocument();
            }
        }

        if (empty($numeroInscricao) && count($order->getPayer()->getDocument())) {
            $firstDoc = $order->getPayer()->getDocument()[0]->getDocument();

            $codigoInscricao = $firstDoc->getDocumentType()->getDocumentType() == 'CNPJ' ? '02' : '01';
            $numeroInscricao = $firstDoc;
        }

        if ($order->getPayer()->getId() == $order->getRetrievePeople()->getId()) {
            $address = $order->getAddressOrigin();
        } else {
            $address = $order->getAddressDestination();
        }

        $enderecoSacado  = $address->getStreet()->getStreet() . ', ' . $address->getNumber() . $address->getComplement() ? ' - ' . $address->getComplement() : '';
        $cepSacado       = str_pad($address->getStreet()->getCep()->getCep(), 8, '0', STR_PAD_LEFT);
        $bairroSacado    = $address->getStreet()->getDistrict()->getDistrict();
        $cidadeSacado    = $address->getStreet()->getDistrict()->getCity()->getCity();
        $estadoSacado    = $address->getStreet()->getDistrict()->getCity()->getState()->getUf();

        $dataVencimento  = $invoice->getDueDate()->format('dmY');
        $urlRetorna      = 'cota-facil.freteclick.com.br/purchasing/order/id/' . $order->getId();
        $observacao      = '';
        $obsAd1          = '';
        $obsAd2          = '';
        $obsAd3          = '';

        $itaucripto      = new \App\Library\Itau\Itaucripto();

        return $itaucripto->geraDados(
            $codEmp,
            $invoice->getId(),
            $valor,
            $observacao,
            $chave,
            $nomeSacado,
            $codigoInscricao,
            $numeroInscricao,
            $enderecoSacado,
            $bairroSacado,
            $cepSacado,
            $cidadeSacado,
            $estadoSacado,
            $dataVencimento,
            $urlRetorna,
            $obsAd1,
            $obsAd2,
            $obsAd3
        );
    }

    /**
     * Retrieve ITAU billing information
     *
     * @param  \ControleOnline\Entity\ReceiveInvoice $invoice
     * @return array
     */
    private function getPayment(Invoice $invoice): array
    {
        if ($invoice->getOrder()->isEmpty())
            throw new ItemNotFoundException('Invoice orders not found');

        $order   = $invoice->getOrder()->first()->getOrder();
        $configs = $this->getItauConfig($order->getProvider());
        $payment = (new ItauClient($invoice, $configs))->getPayment();
        $payinfo = $payment->getAsArray();

        $payinfo['invoiceUrl']        = $configs['itau-shopline-invoice-url'] ?: null;
        $payinfo['status']     = $invoice->getStatus()->getStatus();
        $payinfo['invoiceRealStatus'] = $invoice->getStatus()->getRealStatus();


        // mark order as paid
        if ($payment->isPromissePaid()) {
            $status = $this->manager->getRepository(Status::class)
                ->findOneBy([
                    'status' => 'waiting retrieve'
                ]);

            foreach ($invoice->getOrder() as $orders) {
                $o = $orders->getOrder();
                if ($o->getStatus()->getStatus() == 'waiting payment') {
                    $o->setStatus($status);
                    $o->setNotified(0);
                    $this->manager->persist($o);
                    $this->manager->flush();
                }
            }
        }

        // mark invoice as paid

        if ($payment->isPaid()) {
            $status = $this->manager->getRepository(Status::class)
                ->findOneBy([
                    'status' => 'paid'
                ]);
            $invoice->setStatus($status);

            $this->manager->persist($invoice);
            $this->manager->flush();
        }

        return $payinfo;
    }



    private function getItauConfig(People $people): array
    {
        /**
         * @var \App\Repository\ConfigRepository
         */
        $crepo   = $this->manager->getRepository(Config::class);
        $configs = $crepo->getItauConfigByPeople($people);

        if ($configs === null)
            return [];

        return $configs;
    }
}
