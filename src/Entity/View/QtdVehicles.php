<?php

namespace ControleOnline\Entity\View;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Entity\People;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

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
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['provider' => 'exact'])]
    #[Groups(['productsByDay:read'])]
    private People $provider;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['app' => 'partial'])]
    #[Groups(['productsByDay:read'])]
    private string $app;

    #[ORM\Id]
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false)]
    #[ApiFilter(DateFilter::class, properties: ['date'])]
    #[Groups(['productsByDay:read'])]
    private DateTime $date;

    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['hour' => 'exact'])]
    #[Groups(['productsByDay:read'])]
    private string $hour;

    #[ORM\Column(type: 'integer')]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['quantity' => 'exact'])]
    #[Groups(['productsByDay:read'])]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['total' => 'exact'])]
    #[Groups(['productsByDay:read'])]
    private int $total;

    public function __construct()
    {
        $this->date = new DateTime('now');
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

    public function getApp(): string
    {
        return $this->app;
    }

    public function setApp(string $app): self
    {
        $this->app = $app;
        return $this;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getHour(): string
    {
        return $this->hour;
    }

    public function setHour(string $hour): self
    {
        $this->hour = $hour;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }
}