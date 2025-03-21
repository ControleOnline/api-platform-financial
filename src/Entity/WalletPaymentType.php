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
        new Get(security: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
    ],
    normalizationContext: ['groups' => ['wallet_payment_type:read']],
    denormalizationContext: ['groups' => ['wallet_payment_type:write']]
)]
class WalletPaymentType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @Groups({"wallet:read","wallet_payment_type:read", "wallet_payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Wallet", inversedBy="walletPaymentTypes")
     * @ORM\JoinColumn(name="wallet_id", nullable=false)
     * @Groups({"wallet_payment_type:read", "wallet_payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['wallet' => 'exact'])]
    private $wallet;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\PaymentType", inversedBy="walletPaymentTypes")
     * @ORM\JoinColumn(name="payment_type_id", nullable=false)
     * @Groups({"wallet:read","wallet_payment_type:read", "wallet_payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentType' => 'exact'])]
    private $paymentType;

    /**
     * @ORM\Column(type="string", length=80, nullable=true)
     * @Groups({"wallet:read","wallet_payment_type:read", "wallet_payment_type:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentCode' => 'exact'])]
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
