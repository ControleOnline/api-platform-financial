<?php


namespace ControleOnline\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;

use ControleOnline\Entity\ReceiveInvoice;

/**
 * @method ReceiveInvoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceiveInvoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceiveInvoice[]    findAll()
 * @method ReceiveInvoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceiveInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReceiveInvoice::class);
    }

    public function getSchoolOrderClasses($invoiceId, ?array $search = null, ?array $paginate = null, ?bool $isCount = false)
    {
        $conn = $this->getEntityManager()->getConnection();

        if ($isCount) {
            $sql = 'SELECT COUNT(scc.id) AS total';
        } else {
            $sql  = 'SELECT';
            $sql .= ' scc.id,';
            $sql .= ' scs.lesson_status,';
            $sql .= ' scc.start_prevision,';
            $sql .= ' scc.end_prevision,';
            $sql .= ' tea.type,';
            $sql .= ' ord.price';
        }

        $sql .= ' FROM school_class scc';

        $sql .= ' INNER JOIN school_class_status scs ON scs.id = scc.school_class_status_id';
        $sql .= ' INNER JOIN team tea ON tea.id = scc.team_id';
        $sql .= ' INNER JOIN orders ord ON ord.id = scc.order_id';
        $sql .= ' INNER JOIN order_invoice ori ON ori.order_id = ord.id';
        $sql .= ' INNER JOIN invoice inv ON inv.id = ori.invoice_id';

        $sql .= ' WHERE inv.id = :invoice_id';

        // search

        if (is_array($search)) {
            // @todo
        }

        // pagination

        if (is_array($paginate) && !$isCount) {
            $sql .= sprintf(' LIMIT %s, %s', $paginate['from'], $paginate['limit']);
        }

        $stmt = $conn->prepare($sql);

        // query params

        $params = ['invoice_id' => $invoiceId];

        if (is_array($search)) {
            // @todo
        }

        // get all

        $stmt->execute($params);
        $result = $stmt->fetchAll();

        if (empty($result)) {
            return $isCount ? 0 : [];
        } else {

            // add students

            if (!$isCount) {
                $sql  = '
            SELECT
              scc.id AS class_id,
              peo.id,
              peo.name,
              peo.alias
            FROM school_class scc
             INNER JOIN school_class_status scs ON scs.id = scc.school_class_status_id
             INNER JOIN team tea ON tea.id = scc.team_id
             INNER JOIN orders ord ON ord.id = scc.order_id
             INNER JOIN order_invoice ori ON ori.order_id = ord.id
             INNER JOIN invoice inv ON inv.id = ori.invoice_id
             INNER JOIN people_team pte ON pte.team_id = tea.id AND pte.people_type = \'student\'
             INNER JOIN people peo ON peo.id = pte.people_id
            WHERE inv.id = :invoice_id
          ';
                $stmt = $conn->prepare($sql);

                $stmt->execute(['invoice_id' => $invoiceId]);

                $students = $stmt->fetchAll();
                if (!empty($students)) {
                    foreach ($result as $index => $classOrder) {
                        $classStudents = array_filter($students, function ($student) use ($classOrder) {
                            return $student['class_id'] == $classOrder['id'];
                        });

                        $classStudents = array_values($classStudents);

                        if (!empty($classStudents)) {
                            $result[$index]['students'] = [];
                            $result[$index]['students'] = array_map(function ($stdent) {
                                return [
                                    'id'    => $stdent['id'],
                                    'name'  => $stdent['name'],
                                    'alias' => $stdent['alias']
                                ];
                            }, $classStudents);
                        }
                    }
                }
            }
        }

        return $isCount ? (int) $result[0]['total'] : $result;
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
                     INNER JOIN orders o on oi.order_id = o.id AND o.provider_people_id = :people_id';
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
                    $_value = is_numeric($_value) ? $_value : '%' . $_value . '%';
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

        $rsm->addScalarResult('payer_id', 'payer_id');
        $rsm->addScalarResult('payer_name', 'payer_name');
        $rsm->addScalarResult('payer_alias', 'payer_alias');

        $rsm->addScalarResult('status_id', 'status_id');
        $rsm->addScalarResult('status_status', 'status_status');
        $rsm->addScalarResult('status_real_status', 'status_real_status');
        $rsm->addScalarResult('status_color', 'status_color');

        $nqu = $this->getEntityManager()->createNativeQuery($query, $rsm);

        foreach ($filters as $name => $value) {
            if (!empty($value)) {
                $_value = $value;

                if ($name === 'dateTo') {
                    $_value = $_value . ' 23:59:59';
                }
                if ($name === 'searchBy') {
                    $_value = is_numeric($_value) ? (float) $_value : '%' . $_value . '%';
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
                'invoice_type'       => $invoice['order_type'],
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
                        'payer_id'       => $invoice['payer_id'],
                        'payer_name'     => $invoice['payer_name'],
                        'payer_alias'    => $invoice['payer_alias'],                        
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

        $c = isset($filters['orderId']) ? "o5_" : "o2_";

        $select = "
            i0_.id          AS id,
            i0_.due_date    AS due_date,
            i0_.price       AS price,
            GROUP_CONCAT(DISTINCT(" . $c . ".id) SEPARATOR ',') AS orders,
            p0_.id          AS client_id,
            p0_.name        AS client_name,
            p0_.alias       AS client_alias,
            p1_.id          AS provider_id,
            p1_.name        AS provider_name,
            p1_.alias       AS provider_alias,
            p3_.id          AS payer_id,
            p3_.name        AS payer_name,
            p3_.alias       AS payer_alias,            
            i1_.id          AS status_id,
            i1_.status      AS status_status,
            i1_.real_status AS status_real_status,
            i1_.color       AS status_color,
            o2_.order_type  AS order_type
        ";

        if ($type === 'count') {
            $select = "
                COUNT(DISTINCT i0_.id) AS invoice_count
            ";
        }


        $s = isset($filters['orderId']) ? "
            INNER JOIN order_invoice     o6_ ON i0_.id       = o6_.invoice_id 
            INNER JOIN orders            o5_ ON o5_.id       = o6_.order_id            
            " : "";


        $query  = "
            SELECT
                " . $select . "
            FROM invoice i0_
                INNER JOIN order_invoice     o1_ ON i0_.id       = o1_.invoice_id
                INNER JOIN orders            o2_ ON o2_.id       = o1_.order_id                
                " . $s . "
                INNER JOIN status    i1_ ON i1_.id       = i0_.status_id
                LEFT JOIN  people            p0_ ON p0_.id       = o2_.client_id
                LEFT JOIN  people            p1_ ON p1_.id       = o2_.provider_id
                LEFT JOIN  people            p3_ ON p3_.id       = o2_.payer_people_id
                LEFT JOIN  order_invoice_tax o3_ ON o3_.order_id = o2_.id
                LEFT JOIN  invoice_tax       o4_ ON o4_.id       = o3_.invoice_tax_id
        ";

        if (!empty($filters)) {
            $criteria = [];

            // providerId            
            if (isset($filters['dateFrom']) && $filters['dateFrom']) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'i0_.due_date >= :dateFrom',
                    'added'     => false
                ];
            }


            if (isset($filters['dateTo']) && $filters['dateTo']) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'i0_.due_date <= :dateTo',
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

            if (isset($filters['providerId'])) {
                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => 'o2_.provider_id = :providerId',
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
                    $searchBy .= ' p0_.name LIKE :searchBy';
                    $searchBy .= ' OR p1_.name LIKE :searchBy';
                    $searchBy .= ' OR p0_.alias LIKE :searchBy';
                    $searchBy .= ' OR p1_.alias LIKE :searchBy';
                    $searchBy .= ' OR p3_.name LIKE :searchBy';
                    $searchBy .= ' OR p3_.alias LIKE :searchBy';
                }

                $searchBy .= ')';

                $criteria[] = [
                    'operator'  => 'AND',
                    'condition' => $searchBy,
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
