<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Consolidated cash register ("Caixa") grouped by payment type for a period.
 *
 * Visibility follows the user type:
 *  - ROLE_SUPER and owner/manager links see the whole company;
 *  - any other staff link sees only the invoices created by themselves.
 */
class CashRegisterSummaryService
{
    public const PERIODS = ['today', 'yesterday', 'week', 'month', 'custom'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PeopleService $peopleService,
        private TokenStorageInterface $security
    ) {}

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} [from, to) range
     */
    public static function resolvePeriod(
        string $period,
        ?string $from = null,
        ?string $to = null,
        ?\DateTimeImmutable $now = null
    ): array {
        $today = ($now ?? new \DateTimeImmutable())->setTime(0, 0, 0);

        switch ($period) {
            case 'today':
                return [$today, $today->modify('+1 day')];
            case 'yesterday':
                return [$today->modify('-1 day'), $today];
            case 'week':
                $monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
                return [$monday, $monday->modify('+7 days')];
            case 'month':
                $first = $today->modify('first day of this month');
                return [$first, $first->modify('+1 month')];
            case 'custom':
                $start = self::parseDate($from);
                $end = self::parseDate($to);
                if (!$start || !$end) {
                    throw new BadRequestHttpException('Custom period requires valid from and to (Y-m-d).');
                }
                if ($end < $start) {
                    throw new BadRequestHttpException('Period end must not be before period start.');
                }
                return [$start, $end->modify('+1 day')];
        }

        throw new BadRequestHttpException('Invalid period.');
    }

    private static function parseDate(?string $date): ?\DateTimeImmutable
    {
        if (!$date) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        return $parsed ?: null;
    }

    public function getSummary(int $companyId, string $period, ?string $from, ?string $to, ?string $device = null): array
    {
        $company = $this->entityManager->getRepository(People::class)->find($companyId);
        if (!$company) {
            throw new BadRequestHttpException('Company not found.');
        }

        $user = $this->security->getToken()?->getUser();
        $isSuper = $user && method_exists($user, 'getRoles') && in_array('ROLE_SUPER', $user->getRoles(), true);

        if (!$isSuper && !$this->peopleService->canAccessCompany($company, null, PeopleLink::HUMAN_LINK)) {
            throw new AccessDeniedHttpException('Access denied to this company.');
        }

        $fullAccess = $isSuper
            || $this->peopleService->canAccessCompany($company, null, PeopleLink::ADMIN_LINK);

        [$start, $end] = self::resolvePeriod($period, $from, $to);

        $params = [
            'company' => $companyId,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $sql = "SELECT pt.id AS payment_type_id,
                       COALESCE(pt.payment_type, '-') AS payment_type,
                       SUM(CASE WHEN i.source_wallet_id IS NULL THEN i.price ELSE 0 END) AS inflow,
                       SUM(CASE WHEN i.source_wallet_id IS NOT NULL THEN i.price ELSE 0 END) AS withdrawal,
                       COUNT(i.id) AS quantity
                  FROM invoice i
                  JOIN status s ON s.id = i.status_id AND s.real_status = 'closed'
                  LEFT JOIN payment_type pt ON pt.id = i.payment_type_id
                 WHERE i.receiver_id = :company
                   AND i.invoice_type IN ('invoice', 'payment')
                   AND i.invoice_date >= :start
                   AND i.invoice_date < :end";

        if ($device) {
            $sql .= ' AND i.device_id IN (SELECT d.id FROM device d WHERE d.device = :device)';
            $params['device'] = $device;
        }

        if (!$fullAccess) {
            $sql .= ' AND i.user_id = :user';
            $params['user'] = $user && method_exists($user, 'getId') ? $user->getId() : 0;
        }

        $sql .= ' GROUP BY pt.id, pt.payment_type ORDER BY inflow DESC';

        $rows = $this->entityManager->getConnection()->fetchAllAssociative($sql, $params);

        return self::format($rows, $start, $end, $fullAccess, $fullAccess ? null : (int) ($params['user'] ?? 0));
    }

    public static function format(array $rows, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $fullAccess, ?int $userId = null): array
    {
        $groups = [];
        $total = ['inflow' => 0.0, 'withdrawal' => 0.0, 'balance' => 0.0, 'quantity' => 0];

        foreach ($rows as $row) {
            $inflow = round((float) $row['inflow'], 2);
            $withdrawal = round((float) $row['withdrawal'], 2);
            $quantity = (int) $row['quantity'];

            $groups[] = [
                'paymentTypeId' => $row['payment_type_id'] !== null ? (int) $row['payment_type_id'] : null,
                'paymentType' => $row['payment_type'],
                'inflow' => $inflow,
                'withdrawal' => $withdrawal,
                'balance' => round($inflow - $withdrawal, 2),
                'quantity' => $quantity,
            ];

            $total['inflow'] += $inflow;
            $total['withdrawal'] += $withdrawal;
            $total['quantity'] += $quantity;
        }

        $total['inflow'] = round($total['inflow'], 2);
        $total['withdrawal'] = round($total['withdrawal'], 2);
        $total['balance'] = round($total['inflow'] - $total['withdrawal'], 2);

        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->modify('-1 day')->format('Y-m-d'),
            'scope' => $fullAccess ? 'company' : 'user',
            'userId' => $fullAccess ? null : $userId,
            'payments' => $groups,
            'total' => $total,
        ];
    }
}
