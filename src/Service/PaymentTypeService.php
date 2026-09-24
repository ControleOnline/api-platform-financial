<?php

namespace ControleOnline\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 AS Security;
use Doctrine\ORM\QueryBuilder;

class PaymentTypeService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $PeopleService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $rootAlias = $rootAlias ?: ($queryBuilder->getRootAliases()[0] ?? null);
        if (!$rootAlias) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $peoplePaymentAlias = 'paymentTypePeoplePaymentSecurityFilter';
        if (!in_array($peoplePaymentAlias, $queryBuilder->getAllAliases(), true)) {
            $queryBuilder->leftJoin(sprintf('%s.peoplePayments', $rootAlias), $peoplePaymentAlias);
        }

        // Company scope: legacy payment_type.people_id OR people_payment.people_id
        $this->PeopleService->checkCompany('people', $queryBuilder, $resourceClass, $applyTo, $peoplePaymentAlias);
    }
}
