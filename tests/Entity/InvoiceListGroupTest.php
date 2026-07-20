<?php

namespace ControleOnline\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Attribute\CollectionSummary;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\PaymentType;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Wallet;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final class InvoiceListGroupTest extends TestCase
{
    public function testInvoicesCollectionUsesDedicatedListGroup(): void
    {
        $operation = $this->getCollectionOperation(Invoice::class, '/invoices');

        self::assertSame(
            ['invoice_list:read'],
            $operation->getNormalizationContext()['groups'] ?? null
        );
    }

    public function testInvoiceListKeepsSummaryEnabled(): void
    {
        self::assertContains(
            'invoice_list:read',
            $this->getCollectionSummaryGroups(Invoice::class, 'financialSummary')
        );
    }

    public function testInvoiceListCoversDisplayedFields(): void
    {
        foreach ([
            [Invoice::class, 'id'],
            [Invoice::class, 'status'],
            [Invoice::class, 'payer'],
            [Invoice::class, 'receiver'],
            [Invoice::class, 'invoice_date'],
            [Invoice::class, 'alter_date'],
            [Invoice::class, 'dueDate'],
            [Invoice::class, 'notified'],
            [Invoice::class, 'price'],
            [Invoice::class, 'description'],
            [Invoice::class, 'invoiceType'],
            [Invoice::class, 'category'],
            [Invoice::class, 'sourceWallet'],
            [Invoice::class, 'destinationWallet'],
            [Invoice::class, 'paymentType'],
            [Invoice::class, 'portion'],
            [Invoice::class, 'installments'],
            [People::class, 'id'],
            [People::class, 'name'],
            [People::class, 'alias'],
            [Category::class, 'id'],
            [Category::class, 'name'],
            [Category::class, 'color'],
            [Status::class, 'id'],
            [Status::class, 'status'],
            [Status::class, 'color'],
            [Wallet::class, 'id'],
            [Wallet::class, 'wallet'],
            [PaymentType::class, 'id'],
            [PaymentType::class, 'paymentType'],
            [PaymentType::class, 'frequency'],
        ] as [$class, $property]) {
            self::assertContains(
                'invoice_list:read',
                $this->getGroups($class, $property),
                sprintf('%s::$%s must be part of invoice_list:read', $class, $property)
            );
        }
    }

    public function testInvoiceListDoesNotExpandHeavyPeopleCollections(): void
    {
        foreach ([
            'user',
            'document',
            'company_document',
            'address',
            'phone',
            'email',
        ] as $property) {
            self::assertNotContains(
                'invoice_list:read',
                $this->getGroups(People::class, $property),
                sprintf('People::$%s must stay out of invoice_list:read', $property)
            );
        }
    }

    public function testInvoiceListDoesNotExpandCategoryCompany(): void
    {
        self::assertNotContains(
            'invoice_list:read',
            $this->getGroups(Category::class, 'company'),
            'Category::$company must stay out of invoice_list:read'
        );
    }

    /**
     * @return list<string>
     */
    private function getGroups(string $class, string $property): array
    {
        $reflectionProperty = new ReflectionProperty($class, $property);
        $attributes = $reflectionProperty->getAttributes(Groups::class);
        if ([] === $attributes) {
            return [];
        }

        /** @var Groups $groups */
        $groups = $attributes[0]->newInstance();

        return $groups->getGroups();
    }

    /**
     * @return list<string>
     */
    private function getCollectionSummaryGroups(string $class, string $property): array
    {
        $reflectionProperty = new ReflectionProperty($class, $property);
        $attributes = $reflectionProperty->getAttributes(CollectionSummary::class);
        if ([] === $attributes) {
            return [];
        }

        /** @var CollectionSummary $summary */
        $summary = $attributes[0]->newInstance();

        return $summary->getGroups();
    }

    private function getCollectionOperation(string $class, string $uriTemplate): GetCollection
    {
        $resourceAttribute = $this->getApiResourceAttribute($class);
        foreach ($resourceAttribute->getOperations() as $operation) {
            if (!$operation instanceof GetCollection) {
                continue;
            }

            if ($operation->getUriTemplate() === $uriTemplate) {
                return $operation;
            }
        }

        self::fail(sprintf('Could not find a GetCollection operation for %s', $uriTemplate));
    }

    private function getApiResourceAttribute(string $class): ApiResource
    {
        $reflectionClass = new ReflectionClass($class);
        $attributes = $reflectionClass->getAttributes(ApiResource::class);

        self::assertNotEmpty($attributes, sprintf('%s must be an ApiResource', $class));

        return $attributes[0]->newInstance();
    }
}
