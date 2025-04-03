<?php

namespace ControleOnline\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use ControleOnline\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class InvoiceDataProvider implements ProviderInterface
{

    private $qb;
    private $filters = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ConfigService $configService,
        private CashRegisterService $cashRegisterService
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new \Exception('You should not pass!!!');
        }

        $this->filters = $context['filters'] ?? [];

        $payments = $this->getPayments();


        $this->createBaseQuery();
        $query = $this->qb->getQuery()->getSQL();


        return [[
            'payments' => $payments,
            'filters' =>  $this->filters,
            'query' => $query
        ]];
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
            ->groupBy('ow.id, pt.id, dw.id');

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
            $oWalletId = $row['owalletId'];
            $dWalletId = $oWalletId ?: $row['dwalletId'];
            $paymentTypeId = $row['paymentTypeId'];
            $totalPrice = (float) $row['totalPrice'];

            if (!isset($data['wallet'][$dWalletId]))
                $data['wallet'][$dWalletId] = [
                    'wallet' =>  $row['owallet'] ?: $row['dwallet'],
                    'payment' => [],
                    'total' => 0.0,
                ];


            if ($row['owallet'])
                $data['wallet'][$dWalletId]['withdrawal-wallet'] = $row['dwallet'];



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
                    fn($payment) => $payment['inflow'] - $payment['withdrawal'],
                    $data['wallet'][$dWalletId]['payment']
                )
            );

            if (!isset($data['total']))
                $data['total'] = 0.0;

            $data['total'] += $oWalletId === null ? $totalPrice : -$totalPrice;
        }

        return $data;
    }

    private function applyCommonFilters(): void
    {
        $this->applyDeviceFilter();
        $this->applyCashRegisterFilter();
        $this->applyUserFilter();
        $this->applyReceiverFilter();
    }
    private function applyReceiverFilter(): void
    {
        if (isset($this->filters['receiver']))
            $this->qb->andWhere('i.receiver = :receiver')
                ->setParameter('receiver', $this->filters['receiver']);
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

    private function applyCashRegisterFilter(): void
    {
        if (isset($this->filters['device']) && isset($this->filters['receiver'])) {
            $device = $this->entityManager->getRepository(Device::class)->findOneBy([
                'device' =>  $this->filters['device'],
                'people' =>  $this->filters['receiver'],
            ]);
            $deviceConfig = $device->getConfigs(true);

            if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id']))
                $this->qb->andWhere('i.id > :idGt')
                    ->setParameter('idGt',  $deviceConfig['cash-wallet-open-id']);
        }
    }
}
