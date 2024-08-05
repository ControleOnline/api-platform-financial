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




/**
 * @ORM\Entity
 * @ORM\Table(name="vw_products_by_day")
 */

 #[ApiResource(
    operations: [
        new GetCollection(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
    ],
    normalizationContext: ['groups' => ['productsByDay_read']],
)]
class QtdVehicles
{
    /**
     * @ORM\Id 
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"productsByDay_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['provider' => 'exact'])]
    private $provider;

    /**
     * @ORM\Column(type="string", length=50)
     * @Groups({"productsByDay_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['app' => 'partial'])]
    private $app;

    /**
     * @ORM\Column(type="date")
     * @Groups({"productsByDay_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['alter_date' => 'exact'])]
    private $alter_date;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"productsByDay_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['quantity' => 'exact'])]
    private $quantity;


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
     * Get the value of day_work
     */
    public function getDayWork()
    {
        return $this->day_work;
    }

    /**
     * Set the value of day_work
     */
    public function setDayWork($day_work): self
    {
        $this->day_work = $day_work;

        return $this;
    }

    /**
     * Get the value of total_quantity
     */
    public function getTotalQuantity()
    {
        return $this->total_quantity;
    }

    /**
     * Set the value of total_quantity
     */
    public function setTotalQuantity($total_quantity): self
    {
        $this->total_quantity = $total_quantity;

        return $this;
    }
}