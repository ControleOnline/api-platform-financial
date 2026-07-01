<?php

namespace ControleOnline\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class WalletPaymentTypeService
{
    public function __construct(
        private PeopleService $peopleService,
        private RequestStack $requestStack,
    ) {
    }

    public function securityFilter(
        QueryBuilder $queryBuilder,
        $resourceClass = null,
        $applyTo = null,
        $rootAlias = null
    ): void {
        $rootAlias = $rootAlias ?: ($queryBuilder->getRootAliases()[0] ?? null);
        if (!$rootAlias) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $companies = array_map(
            static fn ($company): int => (int) $company->getId(),
            $this->peopleService->getMyCompanies()
        );

        if ($companies === []) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $walletAlias = 'walletPaymentTypeWalletSecurityFilter';
        if (!in_array($walletAlias, $queryBuilder->getAllAliases(), true)) {
            $queryBuilder->innerJoin(sprintf('%s.wallet', $rootAlias), $walletAlias);
        }

        $paymentTypeAlias = 'walletPaymentTypePaymentTypeSecurityFilter';
        if (!in_array($paymentTypeAlias, $queryBuilder->getAllAliases(), true)) {
            $queryBuilder->innerJoin(sprintf('%s.paymentType', $rootAlias), $paymentTypeAlias);
        }

        $requestedCompanyId = $this->resolveRequestedCompanyId();
        if ($requestedCompanyId !== null) {
            if (!in_array($requestedCompanyId, $companies, true)) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }

            $queryBuilder->andWhere(sprintf('%s.people = :walletPaymentTypeCompany', $walletAlias));
            $queryBuilder->andWhere(
                sprintf('%s.people = :walletPaymentTypeCompany', $paymentTypeAlias),
            );
            $queryBuilder->setParameter('walletPaymentTypeCompany', $requestedCompanyId);

            return;
        }

        $queryBuilder->andWhere(sprintf('%s.people IN(:walletPaymentTypeCompanies)', $walletAlias));
        $queryBuilder->andWhere(
            sprintf('%s.people IN(:walletPaymentTypeCompanies)', $paymentTypeAlias),
        );
        $queryBuilder->setParameter('walletPaymentTypeCompanies', $companies);
    }
    private function resolveRequestedCompanyId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $requestedCompany = $request->query->get('people', $request->query->get('company'));
        if ($requestedCompany === null || $requestedCompany === '') {
            return null;
        }

        $requestedCompanyId = (int) preg_replace('/\D/', '', (string) $requestedCompany);

        return $requestedCompanyId > 0 ? $requestedCompanyId : null;
    }
}
