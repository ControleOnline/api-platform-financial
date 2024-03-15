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
 * Invoice
 *
 * @ORM\EntityListeners ({ControleOnline\Listener\LogListener::class})
 * @ORM\Table (name="invoice")
 * @ORM\Entity (repositoryClass="ControleOnline\Repository\InvoiceRepository")
 */
#[ApiResource(
    operations: [
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/invoice/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: \ControleOnline\Controller\GetBankItauDataAction::class
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
        ),
        new Post(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
            validationContext: ['groups' => ['invoice_write']],
            denormalizationContext: ['groups' => ['invoice_write']]
        ),
        new Put(
            security: 'is_granted(\'ROLE_ADMIN\') or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice_write']],
            denormalizationContext: ['groups' => ['invoice_write']]
        ),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\' or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice_write']],
            denormalizationContext: ['groups' => ['invoice_write']]
        ),
        new Get(
            security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')',
            uriTemplate: '/finance/{id}/download',
            requirements: ['id' => '[\\w-]+'],
            controller: \ControleOnline\Controller\GetBankInterDataAction::class
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/receive/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: \ControleOnline\Controller\GetBankItauDataAction::class
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/receive/{id}/bank/inter/{operation}',
            requirements: ['operation' => '^(download|payment)+$'],
            controller: \ControleOnline\Controller\GetBankInterDataAction::class
        ),

        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/receive/{id}/update-notified',
            validationContext: ['groups' => ['invoice_receive_notified_validation']],
            denormalizationContext: ['groups' => ['invoice_receive_notified_edit']]
        )
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice_read']],
    denormalizationContext: ['groups' => ['invoice_write']]
)]


class Invoice
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"invoice_read","logistic_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\ControleOnline\Entity\PurchasingOrderInvoice", mappedBy="invoice")
     * @Groups({"invoice_read","logistic_read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['order.order' => 'exact'])]
    private $order;
    /**
     * @var \ControleOnline\Entity\Status
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Status")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="status_id", referencedColumnName="id")
     * })
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['status' => 'exact', 'status.realStatus' => 'exact'])]
    private $status;

    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="payer_id", referencedColumnName="id")
     * })
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['payer' => 'exact'])]
    private $payer;

    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="receiver_id", referencedColumnName="id")
     * })
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['receiver' => 'exact'])]
    private $receiver;

    /**
     * @var \DateTime
     * @ORM\Column(name="invoice_date", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['invoice_date' => 'exact'])]
    private $invoice_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="alter_date", type="datetime",  nullable=false, columnDefinition="DATETIME on update CURRENT_TIMESTAMP")
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['alter_date' => 'exact'])]
    private $alter_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="due_date", type="datetime",  nullable=false, columnDefinition="DATE")
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: OrderFilter::class, properties: ['dueDate' => 'DESC'])]
    #[ApiFilter(filterClass: DateFilter::class, properties: ['dueDate'])]
    private $dueDate;

    /**
     * @var boolean
     *
     * @ORM\Column(name="notified", type="boolean",  nullable=false)
     * @Groups({"invoice_pay_notified_edit"})
     * @Assert\Type(
     *  type  ="bool"
     * )
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['notified' => 'exact'])]
    private $notified;

    /**
     * @var float
     *
     * @ORM\Column(name="price", type="float",  nullable=true)
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['price' => 'exact'])]
    private $price;

    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"invoice_read","logistic_read","invoice_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category' => 'exact'])]
    private $category = null;

    public function __construct()
    {
        $this->invoice_date = new \DateTime('now');
        $this->alter_date = new \DateTime('now');
        $this->dueDate = new \DateTime('now');
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
    }
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Add PurchasingOrderInvoice
     *
     * @param \ControleOnline\Entity\PurchasingOrderInvoice $order
     * @return People
     */
    public function addOrder(\ControleOnline\Entity\PurchasingOrderInvoice $order)
    {
        $this->order[] = $order;
        return $this;
    }
    /**
     * Remove PurchasingOrderInvoice
     *
     * @param \ControleOnline\Entity\PurchasingOrderInvoice $order
     */
    public function removeOrder(\ControleOnline\Entity\PurchasingOrderInvoice $order)
    {
        $this->order->removeElement($order);
    }
    /**
     * Get PurchasingOrderInvoice
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getOrder()
    {
        return $this->order;
    }


    /**
     * Set price
     *
     * @param float $price
     * @return Invoice
     */
    public function setPrice($price)
    {
        $this->price = $price;
        return $this;
    }
    /**
     * Get price
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }
    /**
     * Get invoice_date
     *
     * @return \DateTimeInterface
     */
    public function getInvoiceDate()
    {
        return $this->invoice_date;
    }
    /**
     * Get alter_date
     *
     * @return \DateTimeInterface
     */
    public function getAlterDate()
    {
        return $this->alter_date;
    }
    /**
     * Get dueDate
     *
     * @return \DateTimeInterface
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * Set dueDate
     *
     * @param \DateTime $due_date
     * @return Invoice
     */
    public function setDueDate(\DateTime $due_date)
    {
        $this->dueDate = $due_date;
        return $this;
    }

    /**
     * Set status
     *
     * @param \ControleOnline\Entity\Status $status
     * @return Order
     */
    public function setStatus(\ControleOnline\Entity\Status $status = null)
    {
        $this->status = $status;
        return $this;
    }
    /**
     * Get status
     *
     * @return \ControleOnline\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }



    /**
     * Set notified
     *
     * @param string $notified
     * @return Invoice
     */
    public function setNotified($notified)
    {
        $this->notified = $notified;
        return $this;
    }
    /**
     * Get notified
     *
     * @return boolean
     */
    public function getNotified()
    {
        return $this->notified;
    }
    public function setCategory(?Category $category = null)
    {
        $this->category = $category;
        return $this;
    }
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * Get the value of payer
     */
    public function getPayer()
    {
        return $this->payer;
    }

    /**
     * Set the value of payer
     */
    public function setPayer($payer): self
    {
        $this->payer = $payer;

        return $this;
    }

    /**
     * Get the value of receiver
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * Set the value of receiver
     */
    public function setReceiver($receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getDateAsString(\DateTime $date = null): string
    {
        return ($date !== null ? $date : (new \DateTime))->format('Y-m-d');
    }
}
