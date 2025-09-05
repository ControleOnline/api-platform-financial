<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(    
    normalizationContext: ['groups' => ['card:read']],
    denormalizationContext: ['groups' => ['card:read']]
)]
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "card")]
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
    #[Groups(['card:read'])]
    private $document;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_1;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_2;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_3;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $number_group_4;

    #[ORM\Column(type: "blob")]
    #[Groups(['card:read'])]
    private $ccv;

    #[ORM\Column(name: "expiration_date", type: "blob")]
    #[Groups(['card:read'])]
    private $expiration_date;
    /**
     * Get the value of id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the value of id
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of people_id
     */
    public function getPeopleId(): int
    {
        return $this->people_id;
    }

    /**
     * Set the value of people_id
     */
    public function setPeopleId(int $people_id): self
    {
        $this->people_id = $people_id;

        return $this;
    }

    /**
     * Get the value of type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the value of type
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Set the value of document
     */
    public function setDocument($document): self
    {
        $this->document = $document;

        return $this;
    }

    /**
     * Get the value of number_group_1
     */
    public function getNumberGroup1()
    {
        return $this->number_group_1;
    }

    /**
     * Set the value of number_group_1
     */
    public function setNumberGroup1($number_group_1): self
    {
        $this->number_group_1 = $number_group_1;

        return $this;
    }

    /**
     * Get the value of number_group_2
     */
    public function getNumberGroup2()
    {
        return $this->number_group_2;
    }

    /**
     * Set the value of number_group_2
     */
    public function setNumberGroup2($number_group_2): self
    {
        $this->number_group_2 = $number_group_2;

        return $this;
    }

    /**
     * Get the value of number_group_3
     */
    public function getNumberGroup3()
    {
        return $this->number_group_3;
    }

    /**
     * Set the value of number_group_3
     */
    public function setNumberGroup3($number_group_3): self
    {
        $this->number_group_3 = $number_group_3;

        return $this;
    }

    /**
     * Get the value of number_group_4
     */
    public function getNumberGroup4()
    {
        return $this->number_group_4;
    }

    /**
     * Set the value of number_group_4
     */
    public function setNumberGroup4($number_group_4): self
    {
        $this->number_group_4 = $number_group_4;

        return $this;
    }

    /**
     * Get the value of ccv
     */
    public function getCcv()
    {
        return $this->ccv;
    }

    /**
     * Set the value of ccv
     */
    public function setCcv($ccv): self
    {
        $this->ccv = $ccv;

        return $this;
    }

    /**
     * Get the value of expiration_date
     */
    public function getExpirationDate()
    {
        return $this->expiration_date;
    }

    /**
     * Set the value of expiration_date
     */
    public function setExpirationDate($expiration_date): self
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }
}
