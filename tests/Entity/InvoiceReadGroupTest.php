<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\People;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final class InvoiceReadGroupTest extends TestCase
{
    public function testInvoiceReadDoesNotExpandPeopleCollections(): void
    {
        foreach (
            [
                'enable',
                'otherInformations',
                'peopleType',
                'foundationDate',
                'user',
                'document',
                'company_document',
                'address',
                'phone',
                'email',
            ] as $property
        ) {
            self::assertNotContains(
                'invoice:read',
                $this->getGroups(People::class, $property),
                sprintf('People::$%s must stay out of invoice:read', $property)
            );
        }
    }

    public function testInvoiceReadDoesNotExpandCategoryCompany(): void
    {
        self::assertNotContains(
            'invoice:read',
            $this->getGroups(Category::class, 'company'),
            'Category::$company must stay out of invoice:read'
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
}
