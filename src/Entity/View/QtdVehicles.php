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
    normalizationContext: ['groups' => ['productsByDay:read']],
)]
 #[ORM\Table(name: 'vw_products_by_day')]
 #[ORM\Entity]
class QtdVehicles
{
    /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['provider' => 'exact'])]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\People::class)]
    private $provider;

    /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['app' => 'partial'])]
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    private $app;

    /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(DateFilter::class, properties: ['date'])]
    #[ORM\Id]
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    private $date;

     /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['hour' => 'exact'])]
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private $hour;

    /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['quantity' => 'exact'])]
    #[ORM\Column(type: 'integer')]
    private $quantity;


    /**
     * @Groups({"productsByDay:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['total' => 'exact'])]
    #[ORM\Column(type: 'integer')]
    private $total;


    public function __construct()
    {
        $this->date    = new \DateTime('now');
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
     * Get the value of app
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Set the value of app
     */
    public function setApp($app): self
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Get the value of date
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set the value of date
     */
    public function setDate($date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get the value of hour
     */
    public function getHour()
    {
        return $this->hour;
    }

    /**
     * Set the value of hour
     */
    public function setHour($hour): self
    {
        $this->hour = $hour;

        return $this;
    }

    /**
     * Get the value of quantity
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set the value of quantity
     */
    public function setQuantity($quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get the value of total
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Set the value of total
     */
    public function setTotal($total): self
    {
        $this->total = $total;

        return $this;
    }
}