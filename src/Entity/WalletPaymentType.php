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
use ControleOnline\Entity\Wallet;
use ControleOnline\Entity\PaymentType;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_CLIENT')"),
        new Get(security: "is_granted('ROLE_CLIENT')"),
        new Post(security: "is_granted('ROLE_CLIENT')"),
        new Put(security: "is_granted('ROLE_CLIENT')"),
        new Delete(security: "is_granted('ROLE_CLIENT')")
    ],
    normalizationContext: ['groups' => ['wallet_payment_type:read']],
    denormalizationContext: ['groups' => ['wallet_payment_type:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'wallet' => 'exact',
    'paymentType' => 'exact',
    'paymentCode' => 'exact'
])]
class WalletPaymentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['wallet:read', 'wallet_payment_type:read', 'wallet_payment_type:write'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'walletPaymentTypes')]
    #[ORM\JoinColumn(name: 'wallet_id', nullable: false)]
    #[Groups(['wallet_payment_type:read', 'wallet_payment_type:write'])]
    private $wallet;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, inversedBy: 'walletPaymentTypes')]
    #[ORM\JoinColumn(name: 'payment_type_id', nullable: false)]
    #[Groups(['wallet:read', 'wallet_payment_type:read', 'wallet_payment_type:write'])]
    private $paymentType;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    #[Groups(['wallet:read', 'wallet_payment_type:read', 'wallet_payment_type:write'])]
    private $paymentCode;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getWallet()
    {
        return $this->wallet;
    }

    public function setWallet($wallet): self
    {
        $this->wallet = $wallet;
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

    public function getPaymentCode()
    {
        return $this->paymentCode;
    }

    public function setPaymentCode($paymentCode): self
    {
        $this->paymentCode = $paymentCode;
        return $this;
    }
}