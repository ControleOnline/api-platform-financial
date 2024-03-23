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
    normalizationContext: ['groups' => ['payment_type_read']],
    denormalizationContext: ['groups' => ['payment_type_write']]
)]
class PaymentType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @Groups({"invoice_read","payment_type_read", "payment_type_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]

    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"invoice_read","payment_type_read", "payment_type_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['people' => 'exact'])]

    private $people;

    /**
     * @ORM\Column(type="string", length=50)
     * @Groups({"invoice_read","payment_type_read", "payment_type_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentType' => 'partial'])]
    private $paymentType;

    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('monthly', 'daily', 'weekly', 'single')")
     * @Groups({"invoice_read","payment_type_read", "payment_type_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['frequency' => 'exact'])]

    private $frequency;

    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('single', 'split')")
     * @Groups({"invoice_read","payment_type_read", "payment_type_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['installments' => 'exact'])]

    private $installments;

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
     * Get the value of paymentType
     */
    public function getPaymentType()
    {
        return $this->paymentType;
    }

    /**
     * Set the value of paymentType
     */
    public function setPaymentType($paymentType): self
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    /**
     * Get the value of frequency
     */
    public function getFrequency()
    {
        return $this->frequency;
    }

    /**
     * Set the value of frequency
     */
    public function setFrequency($frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    /**
     * Get the value of installments
     */
    public function getInstallments()
    {
        return $this->installments;
    }

    /**
     * Set the value of installments
     */
    public function setInstallments($installments): self
    {
        $this->installments = $installments;

        return $this;
    }
}
