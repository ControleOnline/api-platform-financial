<?php

namespace ControleOnline\Entity\View;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Entity\People;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
    ],
    normalizationContext: ['groups' => ['orderHour:read']],
)]
#[ORM\Table(name: 'vw_orders_by_hour')]
#[ORM\Entity]
class OrderHour
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['order_hour' => 'exact'])]
    #[Groups(['orderHour:read'])]
    private int $order_hour;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['provider' => 'exact'])]
    #[Groups(['orderHour:read'])]
    private People $provider;

    #[ORM\Column(type: 'string', length: 50)]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['average_orders' => 'partial'])]
    #[Groups(['orderHour:read'])]
    private string $average_orders;

    public function getOrderHour(): int
    {
        return $this->order_hour;
    }

    public function setOrderHour(int $order_hour): self
    {
        $this->order_hour = $order_hour;
        return $this;
    }

    public function getProvider(): People
    {
        return $this->provider;
    }

    public function setProvider(People $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getAverageOrders(): string
    {
        return $this->average_orders;
    }

    public function setAverageOrders(string $average_orders): self
    {
        $this->average_orders = $average_orders;
        return $this;
    }
}