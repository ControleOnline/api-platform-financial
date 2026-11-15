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

#[ORM\Entity]
#[ORM\Table(name: 'people_payment')]
#[ORM\UniqueConstraint(name: 'people_payment_unique', columns: ['people_id', 'payment_type_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new Post(security: "is_granted('ROLE_HUMAN')"),
        new Put(security: "is_granted('ROLE_HUMAN')"),
        new Delete(security: "is_granted('ROLE_HUMAN')")
    ],
    normalizationContext: ['groups' => ['people_payment:read']],
    denormalizationContext: ['groups' => ['people_payment:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'people' => 'exact',
    'paymentType' => 'exact',
    'paymentType.paymentType' => 'partial',
])]
class PeoplePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['people_payment:read', 'people_payment:write', 'payment_type:read'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['people_payment:read', 'people_payment:write', 'payment_type:read'])]
    private $people;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, inversedBy: 'peoplePayments')]
    #[ORM\JoinColumn(name: 'payment_type_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['people_payment:read', 'people_payment:write'])]
    private $paymentType;

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
}
