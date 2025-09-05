<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ControleOnline\Controller\Card\CardCollectionController;
use ControleOnline\Controller\Card\CardController;
use ControleOnline\Controller\Card\CardCreateController;
use ControleOnline\Controller\Card\CardDeleteController;
use ControleOnline\Controller\Card\CardUpdateController;
use ControleOnline\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
            controller: CardCollectionController::class
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
            controller: CardController::class
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
            controller: CardCreateController::class
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
            controller: CardUpdateController::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
            controller: CardDeleteController::class
        ),
    ],
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')",
    normalizationContext: ['groups' => ['card:read']],
    denormalizationContext: ['groups' => ['card:write']]
)]

#[ORM\Table(name: "card")]
#[ORM\Entity(repositoryClass: CardRepository::class)]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    #[Groups(['card:read'])]
    private ?int $id = null;

    #[ORM\Column(type: "integer")]
    #[Groups(['card:read'])]
    private int $people_id;

    #[ORM\Column(type: "string")]
    #[Groups(['card:read'])]
    private string $type;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $name;

    #[ORM\Column(type: "blob")]
    private $document;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_1;

    #[ORM\Column(type: "blob")]
    private $number_group_2;

    #[ORM\Column(type: "blob")]
    private $number_group_3;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_4;

    #[ORM\Column(type: "blob")]
    private $ccv;

    #[ORM\Column(name: "expiration_date", type: "blob")]
    private $expiration_date;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPeopleId(): int
    {
        return $this->people_id;
    }

    public function setPeopleId(int $people_id): self
    {
        $this->people_id = $people_id;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function setDocument($document): self
    {
        $this->document = $document;
        return $this;
    }

    public function getNumberGroup1()
    {
        return $this->number_group_1;
    }

    public function setNumberGroup1($number_group_1): self
    {
        $this->number_group_1 = $number_group_1;
        return $this;
    }

    public function getNumberGroup2()
    {
        return $this->number_group_2;
    }

    public function setNumberGroup2($number_group_2): self
    {
        $this->number_group_2 = $number_group_2;
        return $this;
    }

    public function getNumberGroup3()
    {
        return $this->number_group_3;
    }

    public function setNumberGroup3($number_group_3): self
    {
        $this->number_group_3 = $number_group_3;
        return $this;
    }

    public function getNumberGroup4()
    {
        return $this->number_group_4;
    }

    public function setNumberGroup4($number_group_4): self
    {
        $this->number_group_4 = $number_group_4;
        return $this;
    }

    public function getCcv()
    {
        return $this->ccv;
    }

    public function setCcv($ccv): self
    {
        $this->ccv = $ccv;
        return $this;
    }

    public function getExpirationDate()
    {
        return $this->expiration_date;
    }

    public function setExpirationDate($expiration_date): self
    {
        $this->expiration_date = $expiration_date;
        return $this;
    }
}
