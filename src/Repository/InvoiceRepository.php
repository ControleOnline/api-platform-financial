<?php


namespace ControleOnline\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;

/**
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function getMonthlyDRE(People $people, int $year, int $month): array
    {
        // Define o intervalo de datas com base no ano e mês
        $startDate = sprintf('%d-%02d-01', $year, $month); // Primeiro dia do mês
        $endDate = (new \DateTime(sprintf('%d-%02d-01', $year, $month)))->modify('last day of this month')->format('Y-m-d'); // Último dia do mês

        $sql = "SELECT 
                SUM(I.price) AS TOTAL,
                PT.payment_type 
            FROM `invoice` I
            INNER JOIN payment_type PT ON PT.id = I.payment_type_id
            WHERE I.receiver_id = :people_id
            AND I.invoice_date BETWEEN :start_date AND :end_date
            GROUP BY PT.payment_type";

        $conn = $this->getEntityManager()->getConnection();
        $filters = [
            'people_id' => $people->getId(),
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        $result = $conn->executeQuery($sql, $filters)->fetchAllAssociative();

        return $result;
    }


    public function getDRE(People $people, int $year, ?int $month = null): array
    {
        $sql = "SELECT
                :year AS year,
                months.month,
                parent_id,
                parent_category_name,
                category_id,
                category_name,
                report.payer_id,
                report.receiver_id,
               SUM(COALESCE(total_price, 0)) AS total_price
            FROM
                (
                    SELECT 1 AS month
                    UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
                    UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
                ) AS months
            LEFT JOIN vw_invoice_monthly_report AS report ON months.month = report.month
                AND (report.payer_id != report.receiver_id OR report.payer_id IS NULL OR report.receiver_id IS NULL)
                AND report.year = :year 
                AND (report.payer_id = :people_id OR report.receiver_id = :people_id)
                ";
        if ($month)
            $sql .= " AND report.month = :month ";

        $sql .= " GROUP BY
                months.month,
                category_id,
                parent_category_name,
                category_name,
                report.payer_id,
                report.receiver_id,
                parent_id";
        $sql .= "     ORDER BY
                months.month
        ";

        $conn = $this->getEntityManager()->getConnection();
        $filters = ['year' => $year, 'people_id' => $people->getId()];

        if ($month)
            $filters['month'] = $month;

        $result = $conn->executeQuery($sql, $filters)->fetchAllAssociative();

        return $this->organizeData($people, $result);
    }


    private function organizeData($people, array $data): array
    {
        $result = [];

        foreach ($data as $row) {
            $month = $row['month'];
            $parent_id = $row['parent_id'];
            $people_type = $people->getId() == $row['receiver_id'] ? 'receive' : 'pay';

            if (!isset($result[$month]['receive']))
                $result[$month]['receive'] = [];
            if (!isset($result[$month]['pay']))
                $result[$month]['pay'] = [];

            if (!isset($result[$month][$people_type]['total_month_price']))
                $result[$month][$people_type]['total_month_price'] = 0;

            if (!isset($result[$month][$people_type]['parent_categories'][$parent_id]['total_parent_category_price']))
                $result[$month][$people_type]['parent_categories'][$parent_id]['total_parent_category_price'] = 0;
            if (!isset($result[$month][$people_type]['parent_categories'][$parent_id]['categories_childs'][$row['category_id']]['category_price']))
                $result[$month][$people_type]['parent_categories'][$parent_id]['categories_childs'][$row['category_id']]['category_price'] = 0;

            $result[$month][$people_type]['total_month_price'] += $row['total_price'];
            $result[$month][$people_type]['parent_categories'][$parent_id]['categories_childs'][$row['category_id']] = [
                'category_id' => $row['category_id'],
                'category_name' => $row['category_name'],
                'category_price' => $result[$month][$people_type]['parent_categories'][$parent_id]['categories_childs'][$row['category_id']]['category_price']                 + $row['total_price']
            ];
            $result[$month][$people_type]['parent_categories'][$parent_id]['parent_id'] = $row['parent_id'];
            $result[$month][$people_type]['parent_categories'][$parent_id]['parent_category_name'] = $row['parent_category_name'];
            $result[$month][$people_type]['parent_categories'][$parent_id]['total_parent_category_price']  += $row['total_price'];
        }

        return $result;
    }
}
