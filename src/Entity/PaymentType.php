<?php
namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ControleOnline\Entity\People;
use ControleOnline\Entity\WalletPaymentType;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new Post(security: "is_granted('ROLE_HUMAN')"),
        new Put(security: "is_granted('ROLE_HUMAN')"),
        new Delete(security: "is_granted('ROLE_HUMAN')")
    ],
    normalizationContext: ['groups' => ['payment_type:read']],
    denormalizationContext: ['groups' => ['payment_type:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'people' => 'exact',
    'paymentType' => 'partial',
    'frequency' => 'exact',
    'installments' => 'exact'
])]
class PaymentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice:read', 'invoice_list:read', 'wallet:read', 'wallet_payment_type:read', 'invoice_details:read', 'payment_type:read', 'payment_type:write', 'order_invoice_invoice:read'])]
    private $id;

    /**
     * @deprecated Ownership moved to people_payment. Kept nullable for backward compatibility.
     */
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment_type:read', 'payment_type:write'])]
    private $people;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['invoice:read', 'invoice_list:read', 'wallet:read', 'wallet_payment_type:read', 'invoice_details:read', 'payment_type:read', 'payment_type:write', 'order_invoice_invoice:read'])]
    private $paymentType;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('monthly', 'daily', 'weekly', 'single')")]
    #[Groups(['invoice:read', 'invoice_list:read', 'wallet:read', 'wallet_payment_type:read', 'invoice_details:read', 'payment_type:read', 'payment_type:write', 'order_invoice_invoice:read'])]
    private $frequency;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('single', 'split')")]
    #[Groups(['invoice:read', 'invoice_list:read', 'wallet:read', 'wallet_payment_type:read', 'invoice_details:read', 'payment_type:read', 'payment_type:write', 'order_invoice_invoice:read'])]
    private $installments;

    #[ORM\OneToMany(targetEntity: WalletPaymentType::class, mappedBy: 'paymentType')]
    #[Groups(['payment_type:read'])]
    private $walletPaymentTypes;

    #[ORM\OneToMany(targetEntity: PeoplePayment::class, mappedBy: 'paymentType')]
    #[Groups(['payment_type:read'])]
    private $peoplePayments;

    public function __construct()
    {
        $this->walletPaymentTypes = new ArrayCollection();
        $this->peoplePayments = new ArrayCollection();
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

    public function getPeoplePayments(): Collection
    {
        return $this->peoplePayments;
    }

    public function addPeoplePayment(PeoplePayment $peoplePayment): self
    {
        if (!$this->peoplePayments->contains($peoplePayment)) {
            $this->peoplePayments[] = $peoplePayment;
            $peoplePayment->setPaymentType($this);
        }
        return $this;
    }

    public function removePeoplePayment(PeoplePayment $peoplePayment): self
    {
        if ($this->peoplePayments->removeElement($peoplePayment)) {
            if ($peoplePayment->getPaymentType() === $this) {
                $peoplePayment->setPaymentType(null);
            }
        }
        return $this;
    }
}
