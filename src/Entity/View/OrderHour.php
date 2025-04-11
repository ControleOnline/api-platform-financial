<?php

namespace ControleOnline\Entity\View;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;

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
    /**
     * @Groups({"orderHour:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['order_hour' => 'exact'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]

    private $order_hour;

    /**
     * @Groups({"orderHour:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['provider' => 'exact'])]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\People::class)]

    private $provider;

    /**
     * @Groups({"orderHour:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['average_orders' => 'partial'])]
    #[ORM\Column(type: 'string', length: 50)]

    private $average_orders;



    /**
     * Get the value of order_hour
     */
    public function getOrderHour()
    {
        return $this->order_hour;
    }

    /**
     * Set the value of order_hour
     */
    public function setOrderHour($order_hour): self
    {
        $this->order_hour = $order_hour;

        return $this;
    }

    /**
     * Get the value of provider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the value of provider
     */
    public function setProvider($provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Get the value of average_orders
     */
    public function getAverageOrders()
    {
        return $this->average_orders;
    }

    /**
     * Set the value of average_orders
     */
    public function setAverageOrders($average_orders): self
    {
        $this->average_orders = $average_orders;

        return $this;
    }
}