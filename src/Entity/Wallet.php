<?php

namespace ControleOnline\Entity;

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
 */

#[ApiResource(
    operations: [
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Post(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Delete(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
    ],
    normalizationContext: ['groups' => ['wallet_read']],
    denormalizationContext: ['groups' => ['wallet_write']]
)]
class Wallet
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @Groups({"invoice_read", "wallet_read", "wallet_write"})

     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]

    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"wallet_read", "wallet_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['people' => 'exact'])]

    private $people;

    /**
     * @ORM\Column(type="string", length=50)
     * @Groups({"invoice_read", "wallet_read", "wallet_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['wallet' => 'partial'])]

    private $wallet;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"invoice_read", "wallet_read", "wallet_write"})
     */
    private $balance = 0;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of people
     */
    public function getPeople()
    {
        return $this->people;
    }

    /**
     * Set the value of people
     */
    public function setPeople($people): self
    {
        $this->people = $people;

        return $this;
    }

    /**
     * Get the value of wallet
     */
    public function getWallet()
    {
        return $this->wallet;
    }

    /**
     * Set the value of wallet
     */
    public function setWallet($wallet): self
    {
        $this->wallet = $wallet;

        return $this;
    }

    /**
     * Get the value of balance
     */
    public function getBalance()
    {
        return $this->balance;
    }

    /**
     * Set the value of balance
     */
    public function setBalance($balance): self
    {
        $this->balance = $balance;

        return $this;
    }
}
