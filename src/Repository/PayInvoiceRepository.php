<?php


namespace App\Repository;

use ControleOnline\Entity\PayInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;

class PayInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayInvoice::class);
    }

    /**
     * @param $peopleId
     * @return Invoice[]
     * @throws DBALException
     */
    public function findInvoicesByPeople($peopleId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql =
            'SELECT i.*, s.real_status, s.status
            FROM invoice i
                     INNER JOIN status s on i.status_id = s.id
                     INNER JOIN order_invoice oi on i.id = oi.invoice_id
                     INNER JOIN orders o on oi.order_id = o.id AND o.payer_people_id = :people_id';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['people_id' => $peopleId]);

        return $stmt->fetchAll();
    }


    public function getInvoiceCollectionCount(array $filters = []): int
    {
        // build query string

        $query = $this->createInvoiceCollectionQuery($filters, 'count');

        // mapping

        $rsm = new ResultSetMapping();

        $rsm->addScalarResult('invoice_count', 'invoice_count', 'integer');

        $nqu = $this->getEntityManager()->createNativeQuery($query, $rsm);

        foreach ($filters as $name => $value) {
            if (!empty($value)) {
                $_value = $value;

                if ($name === 'searchBy') {
                    $_value = is_numeric($_value) ? (float) $_value : '%' . $_value . '%';
                }

                $nqu->setParameter($name, $_value);
            }
        }

        $count = $nqu->getArrayResult();

        return isset($count[0]) ? $count[0]['invoice_count'] : 0;
    }

    public function getInvoiceCollection(array $filters = [], int $from = 0, int $limit = 10): array
    {
        // build query string

        $query  = $this->createInvoiceCollectionQuery($filters, 'rows');
        $query .= '
            ORDER BY i0_.due_date ASC
            LIMIT :from, :limit
        ';



        // mapping

        $rsm = new ResultSetMapping();

        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('order_type', 'order_type');
        $rsm->addScalarResult('due_date', 'due_date');
        $rsm->addScalarResult('price', 'price');
        $rsm->addScalarResult('orders', 'orders');
        $rsm->addScalarResult('client_id', 'client_id');
        $rsm->addScalarResult('client_name', 'client_name');
        $rsm->addScalarResult('client_alias', 'client_alias');
        $rsm->addScalarResult('provider_id', 'provider_id');
        $rsm->addScalarResult('provider_name', 'provider_name');
        $rsm->addScalarResult('provider_alias', 'provider_alias');
        $rsm->addScalarResult('status_id', 'status_id');
        $rsm->addScalarResult('status_status', 'status_status');
        $rsm->addScalarResult('status_real_status', 'status_real_status');
        $rsm->addScalarResult('status_color', 'status_color');
        $rsm->addScalarResult('category_id', 'category_id');
        $rsm->addScalarResult('category_name', 'category_name');
        $rsm->addScalarResult('category_context', 'category_context');
        $rsm->addScalarResult('description', 'description');

        $nqu = $this->getEntityManager()->createNativeQuery($query, $rsm);

        foreach ($filters as $name => $value) {
            if (!empty($value)) {
                $_value = $value;

                if ($name === 'searchBy') {
                    $_value = is_numeric($_value) ? $_value : '%' . $_value . '%';
                }
                if ($name === 'to_date') {
                    $_value = $_value . ' 23:59:59';
                }
                $nqu->setParameter($name, $_value);
            }
        }
        $nqu->setParameter('from', $from);
        $nqu->setParameter('limit', $limit);


        // adjust result

        $output = [];

        foreach ($nqu->getArrayResult() as $invoice) {
            $output[$invoice['id']] = [
                'id'                 => $invoice['id'],
                'price'              => $invoice['price'],
                'due_date'           => $invoice['due_date'],
                'status_id'          => $invoice['status_id'],
                'status_status'      => $invoice['status_status'],
                'status_real_status' => $invoice['status_real_status'],
                'status_color'       => $invoice['status_color'],
                'category_id'        => $invoice['category_id'],
                'category_name'      => $invoice['category_name'],
                'category_context'   => $invoice['category_context'],
                'invoice_type'       => $invoice['order_type'],
                'description'        => $invoice['description'],
                'orders'             => []
            ];

            if (!empty($invoice['orders'])) {
                $orders = explode(',', $invoice['orders']);
                foreach ($orders as $orderId) {
                    $output[$invoice['id']]['orders'][] = [
                        'id'             => $orderId,
                        'client_id'      => $invoice['client_id'],
                        'client_name'    => $invoice['client_name'],
                        'client_alias'   => $invoice['client_alias'],
                        'provider_id'    => $invoice['provider_id'],
                        'provider_name'  => $invoice['provider_name'],
                        'provider_alias' => $invoice['provider_alias'],
                        'order_type'     => $invoice['order_type'],
                    ];
                }
            }
        }

        return $output;
    }

    private function createInvoiceCollectionQuery(array $filters, string $type = 'rows'): string
    {
        $select = "
            i0_.id          AS id,
            i0_.due_date    AS due_date,
            i0_.price       AS price,
            GROUP_CONCAT(DISTINCT o2_.id SEPARATOR ',') AS orders,
            p0_.id          AS client_id,
            p0_.name        AS client_name,
            p0_.alias       AS client_alias,
            p1_.id          AS provider_id,
            p1_.name        AS provider_name,
            p1_.alias       AS provider_alias,
            i1_.id          AS status_id,
            i1_.status      AS status_status,
            i1_.real_status AS status_real_status,
            i1_.color       AS status_color,
            ca_.id          AS category_id,
            ca_.name        AS category_name,
            ca_.context     AS category_context,
            o2_.order_type  AS order_type,
            i0_.description AS description
        ";

        if ($type === 'count') {
            $select = "
                COUNT(DISTINCT i0_.id) AS invoice_count
            ";
        }

        $query  = "
            SELECT
                " . $select . "
            FROM invoice i0_
                INNER JOIN order_invoice     o1_ ON i0_.id            = o1_.invoice_id
                INNER JOIN orders            o2_ ON o2_.id            = o1_.order_id
                INNER JOIN status    i1_ ON i1_.id            = i0_.status_id
                LEFT JOIN  category          ca_ ON i0_.category_id   = ca_.id
                LEFT JOIN  orders            o5_ ON o5_.id            = o2_.main_order_id
                LEFT JOIN  people            p0_ ON p0_.id            = o2_.client_id
                LEFT JOIN  people            p1_ ON p1_.id            = o2_.provider_id
                LEFT JOIN  order_invoice_tax o3_ ON o3_.order_id      = o5_.id
                LEFT JOIN  invoice_tax       o4_ ON o4_.id            = o3_.invoice_tax_id
        ";

        if (!empty($filters)) {
            $criteria = [];

            // clientId

            if (isset($filters['clientId'])) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => '( o2_.client_id = :clientId OR o2_.payer_people_id = :clientId )',
                    'added'     => false
                ];
            }

            // searchBy

            if (isset($filters['searchBy'])) {
                $searchBy  = '(';

                if (is_numeric($filters['searchBy'])) {
                    $searchBy .= sprintf(' o4_.invoice_number = %d', ((float) $filters['searchBy']));
                    $searchBy .= sprintf(' OR i0_.price = %d', ((float) $filters['searchBy']));
                } else {
                    $searchBy .= ' p0_.name             LIKE :searchBy';
                    $searchBy .= ' OR p1_.name          LIKE :searchBy';
                    $searchBy .= ' OR p0_.alias         LIKE :searchBy';
                    $searchBy .= ' OR p1_.alias         LIKE :searchBy';
                    $searchBy .= ' OR ca_.name          LIKE :searchBy';
                    $searchBy .= ' OR i0_.description   LIKE :searchBy';
                    
                }

                $searchBy .= ')';

                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => $searchBy,
                    'added'     => false
                ];
            }



            if ($filters['from_date']) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'i0_.due_date >= :from_date',
                    'added'     => false
                ];
            }


            if ($filters['to_date']) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'i0_.due_date <= :to_date',
                    'added'     => false
                ];
            }

            if (isset($filters['orderType']) && $filters['orderType']) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'o2_.order_type = :orderType',
                    'added'     => false
                ];
            }

            // invoice status id

            if (isset($filters['status'])) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'i0_.status_id = :status',
                    'added'     => false
                ];
            }

            // order id

            if (isset($filters['orderId'])) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'o2_.id = :orderId',
                    'added'     => false
                ];
            }

            // invoice status realStatus

            if (isset($filters['realStatus'])) {
                if (is_array($filters['realStatus'])) {
                    $criteria[] = [
                        'operator'  => 'AND',
                        'condition' => 'i1_.real_status IN (:realStatus)',
                        'added'     => false
                    ];
                }
            }

            for ($i = 0; $i < count($criteria); $i++) {
                if ($i === 0) {
                    $query .= 'WHERE ';
                    $query .= $criteria[$i]['condition'];

                    $criteria[$i]['added'] = true;
                }

                if (!$criteria[$i]['added']) {
                    $query .= sprintf(' %s %s', $criteria[$i]['operator'], $criteria[$i]['condition']);

                    $criteria[$i]['added'] = true;
                }
            }
        }

        if ($type === 'count') {
            return $query;
        }

        return $query . ' GROUP BY i0_.id, p0_.id ';
    }
}
