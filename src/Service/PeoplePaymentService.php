<?php

namespace ControleOnline\Service;

use Doctrine\ORM\QueryBuilder;

class PeoplePaymentService
{
    public function __construct(
        private PeopleService $PeopleService
    ) {
    }

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $this->PeopleService->checkCompany('people', $queryBuilder, $resourceClass, $applyTo, $rootAlias);
    }
}
