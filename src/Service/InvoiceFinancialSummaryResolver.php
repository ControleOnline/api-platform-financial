<?php

namespace ControleOnline\Service;

use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

class InvoiceFinancialSummaryResolver implements CollectionSummaryResolverInterface
{
    public function __construct(private EntityManagerInterface $manager) {}

    public function resolve(
        Operation $operation,
        string $resourceClass,
        array $summaryField,
        QueryBuilder $filteredIdsQueryBuilder,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        if (Invoice::class !== $resourceClass) {
            return null;
        }

        $summaryAlias = 'summary_invoice';
        $summaryQueryBuilder = $this->manager->createQueryBuilder();
        $summaryQueryBuilder
            ->select(
                sprintf('COALESCE(SUM(%s.price), 0) AS totalAmount', $summaryAlias),
                sprintf(
                    "COALESCE(SUM(CASE WHEN summary_status.realStatus = :paidStatus THEN %s.price ELSE 0 END), 0) AS paidAmount",
                    $summaryAlias
                ),
                sprintf(
                    "COALESCE(SUM(CASE WHEN summary_status.realStatus = :paidStatus THEN 0 ELSE %s.price END), 0) AS openAmount",
                    $summaryAlias
                )
            )
            ->from(Invoice::class, $summaryAlias)
            ->leftJoin(sprintf('%s.status', $summaryAlias), 'summary_status')
            ->andWhere(
                $summaryQueryBuilder->expr()->in(
                    sprintf('%s.id', $summaryAlias),
                    $filteredIdsQueryBuilder->getDQL()
                )
            )
            ->setParameter('paidStatus', 'paid');

        foreach ($filteredIdsQueryBuilder->getParameters() as $parameter) {
            if ($parameter->typeWasSpecified()) {
                $summaryQueryBuilder->setParameter(
                    $parameter->getName(),
                    $parameter->getValue(),
                    $parameter->getType()
                );

                continue;
            }

            $summaryQueryBuilder->setParameter($parameter->getName(), $parameter->getValue());
        }

        $result = $summaryQueryBuilder->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY) ?: [];

        return [
            'totalAmount' => (float) ($result['totalAmount'] ?? 0),
            'paidAmount' => (float) ($result['paidAmount'] ?? 0),
            'openAmount' => (float) ($result['openAmount'] ?? 0),
        ];
    }
}
