<?php

namespace ControleOnline\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ControleOnline\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class InvoiceDataProvider implements ProviderInterface
{
    private $entityManager;
    private $security;
    private $qb;
    private $filters = [];

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new \Exception('You should not pass!!!');
        }

        $this->filters = $context['filters'] ?? [];

        $payments = $this->getPayments();

        return [['payments' => $payments]];
    }

    private function createBaseQuery()
    {
        $this->qb = $this->entityManager->createQueryBuilder()
            ->select('SUM(i.price) as totalPrice')
            ->addSelect('dw.id as dwalletId', 'dw.wallet as dwallet')
            ->addSelect('ow.id as owalletId', 'ow.wallet as owallet')
            ->addSelect('pt.id as paymentTypeId', 'pt.paymentType')
            ->from(Invoice::class, 'i')
            ->join('i.destinationWallet', 'dw')
            ->join('i.paymentType', 'pt')
            ->leftJoin('i.sourceWallet', 'ow')
            ->groupBy('dw.id, ow.id, pt.id');

        $this->applyCommonFilters();
    }

    private function getPayments(): array
    {
        $this->createBaseQuery();
        $results = $this->qb->getQuery()->getResult();
        return $this->getResult($results);
    }

    private function getResult($results): array
    {
        $data = [];
        foreach ($results as $row) {
            $dWalletId = $row['dwalletId'];
            $oWalletId = $row['owalletId'];
            $paymentTypeId = $row['paymentTypeId'];
            $totalPrice = (float) $row['totalPrice'];

            if (!isset($data['wallet'][$dWalletId]))
                $data['wallet'][$dWalletId] = [
                    'wallet' => $row['owallet'] ?: $row['dwallet'],
                    'withdrawal-wallet' => $row['owallet'] ? $row['dwallet'] : $row['owallet'],
                    'payment' => [],
                    'total' => 0.0,
                ];


            if (!isset($data['wallet'][$dWalletId]['payment'][$paymentTypeId])) {
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId] = [
                    'payment' => $row['paymentType'],
                    'inflow' => 0.0,
                    'withdrawal' => 0,
                ];
            }

            if ($oWalletId === null)

                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['inflow'] += $totalPrice;
            else
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['withdrawal'] += $totalPrice;


            $data['wallet'][$dWalletId]['total'] = array_sum(
                array_map(
                    fn($payment) => $payment['inflow'] + $payment['withdrawal'],
                    $data['wallet'][$dWalletId]['payment']
                )
            );

            if (!isset($data['total']))
                $data['total'] = 0.0;

            $data['total'] += $oWalletId === null ? $totalPrice : -$totalPrice;
        }

        return $data;
    }

    private function applyCommonFilters()
    {
        $this->applyDeviceFilter();
        $this->applyIdGtFilter();
        $this->applyUserFilter();
    }

    private function applyUserFilter(): void
    {
        $this->qb->andWhere('i.user = :user')
            ->setParameter('user', $this->security->getUser());
    }

    private function applyDeviceFilter(): void
    {
        if (isset($this->filters['device']))
            $this->qb->andWhere('i.device = :device')
                ->setParameter('device', $this->filters['device']);
    }

    private function applyIdGtFilter(): void
    {
        if (isset($this->filters['id_gt']))
            $this->qb->andWhere('i.id > :idGt')
                ->setParameter('idGt', (int) $this->filters['id_gt']);
    }
}
