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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
    ],
    normalizationContext: ['groups' => ['payment_type:read']],
    denormalizationContext: ['groups' => ['payment_type:write']]
)]
class PaymentType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @Groups({"invoice:read","wallet_payment_type:read","invoice_details:read","payment_type:read", "payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"payment_type:read", "payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['people' => 'exact'])]
    private $people;

    /**
     * @ORM\Column(type="string", length=50)
     * @Groups({"invoice:read","wallet_payment_type:read","invoice_details:read","payment_type:read", "payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentType' => 'partial'])]
    private $paymentType;

    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('monthly', 'daily', 'weekly', 'single')")
     * @Groups({"invoice:read","wallet_payment_type:read","invoice_details:read","payment_type:read", "payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['frequency' => 'exact'])]
    private $frequency;

    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('single', 'split')")
     * @Groups({"invoice:read","wallet_payment_type:read","invoice_details:read","payment_type:read", "payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['installments' => 'exact'])]
    private $installments;

    /**
     * @ORM\OneToMany(targetEntity="ControleOnline\Entity\WalletPaymentType", mappedBy="paymentType")
     * @Groups({"payment_type:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['installments' => 'exact'])]

    private $walletPaymentTypes;

    public function __construct()
    {
        $this->walletPaymentTypes = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPeople()
    {
        return $this->people;
    }

    public function setPeople($people): self
    {
        $this->people = $people;
        return $this;
    }

    public function getPaymentType()
    {
        return $this->paymentType;
    }

    public function setPaymentType($paymentType): self
    {
        $this->paymentType = $paymentType;
        return $this;
    }

    public function getFrequency()
    {
        return $this->frequency;
    }

    public function setFrequency($frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getInstallments()
    {
        return $this->installments;
    }

    public function setInstallments($installments): self
    {
        $this->installments = $installments;
        return $this;
    }

    public function getWalletPaymentTypes(): Collection
    {
        return $this->walletPaymentTypes;
    }

    public function addWalletPaymentType(WalletPaymentType $walletPaymentType): self
    {
        if (!$this->walletPaymentTypes->contains($walletPaymentType)) {
            $this->walletPaymentTypes[] = $walletPaymentType;
            $walletPaymentType->setPaymentType($this);
        }
        return $this;
    }

    public function removeWalletPaymentType(WalletPaymentType $walletPaymentType): self
    {
        if ($this->walletPaymentTypes->removeElement($walletPaymentType)) {
            if ($walletPaymentType->getPaymentType() === $this) {
                $walletPaymentType->setPaymentType(null);
            }
        }
        return $this;
    }
}