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
        if (!$currentUser && !$this->security->isGranted('ROLE_ADMIN'))
            throw new \Exception('You should not pass!!!');


        $this->filters = $context['filters'] ?? [];

        $inflow = $this->getInflow();
        $withdrawal = $this->getWithdrawal();

        return [
            'inflow' => $inflow,
            'withdrawal' => $withdrawal,
            'total' => ((isset($inflow['total']) ? $inflow['total'] : 0) - (isset($withdrawal['total']) ? $withdrawal['total'] : 0))
        ];
    }

    private function createBaseQuery()
    {
        $this->qb = $this->entityManager->createQueryBuilder()
            ->select('SUM(i.price) as totalPrice')
            ->addSelect('w.id as walletId', 'w.wallet')
            ->addSelect('pt.id as paymentTypeId', 'pt.paymentType')
            ->from(Invoice::class, 'i')
            ->join('i.destinationWallet', 'w')
            ->join('i.paymentType', 'pt')
            ->groupBy('w.id, pt.id');

        $this->applyCommonFilters();
    }

    private function getWithdrawal(): array
    {
        $this->createBaseQuery();
        $this->qb->andWhere('i.sourceWallet IS NOT NULL');
        $results = $this->qb->getQuery()->getResult();
        return $this->getResult('withdrawal', $results);
    }

    private function getInflow(): array
    {
        $this->createBaseQuery();
        $this->qb->andWhere('i.sourceWallet IS NULL');
        $results = $this->qb->getQuery()->getResult();
        return $this->getResult('inflow', $results);
    }

    private function getResult($source, $results)
    {
        $data = [];
        foreach ($results as $row) {
            $walletId = $row['walletId'];
            $paymentTypeId = $row['paymentTypeId'];
            $totalPrice = (float) $row['totalPrice'];

            if (!isset($data[$source]['wallet'][$walletId]))
                $data[$source]['wallet'][$walletId] = [
                    'wallet' => $row['wallet'],
                    'payment' => [],
                    'total' => 0.0,
                ];

            if (!isset($data[$source]['wallet'][$walletId]['payment'][$paymentTypeId]))
                $data[$source]['wallet'][$walletId]['payment'][$paymentTypeId] = [
                    'payment' => $row['paymentType'],
                    'total' => 0.0,
                ];

            if (!isset($data[$source]['total']))
                $data[$source]['total'] = 0.0;

            $data[$source]['wallet'][$walletId]['payment'][$paymentTypeId]['total'] += $totalPrice;
            $data[$source]['wallet'][$walletId]['total'] += $totalPrice;
            $data[$source]['total'] += $totalPrice;
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
