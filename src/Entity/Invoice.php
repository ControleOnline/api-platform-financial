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
use ControleOnline\Controller\AsaasWebhookController;
use ControleOnline\Controller\BitcoinController;
use ControleOnline\Controller\GetCashRegisterController;
use ControleOnline\Controller\IncomeStatementAction;
use ControleOnline\Controller\PaylistController;
use ControleOnline\Controller\PixController;
use ControleOnline\Controller\PrintCashRegisterController;
use ControleOnline\DataProvider\InvoiceDataProvider;
use stdClass;

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
            normalizationContext: ['groups' => ['invoice_details:read']],
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/invoice/inflow',
            provider: InvoiceDataProvider::class,
            normalizationContext: ['groups' => ['invoice:read']],
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/cash-register',
            controller: GetCashRegisterController::class
        ),
        new Post(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/cash-register/print',
            controller: PrintCashRegisterController::class
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/invoice/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: \ControleOnline\Controller\GetBankItauDataAction::class
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/income_statements',
            controller: IncomeStatementAction::class
        ),
        new GetCollection(
            uriTemplate: '/paylist',
            security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')',
            controller: PaylistController::class,
            openapiContext: [
                'summary' => 'Retrieve invoices based on document and company.',
            ],
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
        ),
        new Post(
            security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')',
            uriTemplate: '/pix',
            controller: PixController::class,
        ),
        new Post(
            security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')',
            uriTemplate: '/bitcoin',
            controller: BitcoinController::class,
        ),
        new Post(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
            validationContext: ['groups' => ['invoice:write']],
            denormalizationContext: ['groups' => ['invoice:write']],
            uriTemplate: '/invoices',

        ),
        new Put(
            security: 'is_granted(\'ROLE_ADMIN\') or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice:write']],
            denormalizationContext: ['groups' => ['invoice:write']]
        ),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\' or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice:write']],
            denormalizationContext: ['groups' => ['invoice:write']]
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
        ),
        new Put(
            security: 'is_granted(\'ROLE_ADMIN\') or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice:write']],
            denormalizationContext: ['groups' => ['invoice:write']],
            uriTemplate: '/invoice/{id}/split',
            controller: \ControleOnline\Controller\SplitInvoiceAction::class
        ),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']]
)]


class Invoice
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"invoice:read","invoice_details:read","logistic:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\ControleOnline\Entity\OrderInvoice", mappedBy="invoice")
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
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
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
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
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
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['receiver' => 'exact'])]
    private $receiver;

    /**
     * @var \DateTime
     * @ORM\Column(name="invoice_date", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['invoice_date' => 'exact'])]
    private $invoice_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="alter_date", type="datetime",  nullable=false, columnDefinition="DATETIME on update CURRENT_TIMESTAMP")
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['alter_date' => 'exact'])]
    private $alter_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="due_date", type="datetime",  nullable=false, columnDefinition="DATE")
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(OrderFilter::class, properties: ['dueDate' => 'DESC', 'id' => 'DESC'])]
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
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['notified' => 'exact'])]
    private $notified  = false;

    /**
     * @var float
     *
     * @ORM\Column(name="price", type="float",  nullable=true)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
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
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category' => 'exact'])]
    private $category = null;


    /**
     * @ORM\Column(type="json")
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    private $otherInformations;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Wallet")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['sourceWallet' => 'exact'])]

    private $sourceWallet;


    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Wallet")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['destinationWallet' => 'exact'])]

    private $destinationWallet;
    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\PaymentType")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentType' => 'exact'])]
    private $paymentType;

    /**
     * @var integer
     *
     * @ORM\Column(name="portion_number", type="integer",  nullable=false)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    private $portion;

    /**
     * @var integer
     *
     * @ORM\Column(name="installments", type="integer",  nullable=false)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    private $installments;


    /**
     * @var integer
     *
     * @ORM\Column(name="installment_id", type="integer",  nullable=true)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    private $installment_id;

    /**
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\User")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"invoice:read","invoice_details:read","logistic:read","invoice:write","order_invoice:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['user' => 'exact'])]
    private $user;

    /**
     * @var \ControleOnline\Entity\Device
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Device")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="device_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"device_config:read","device:read","device_config:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['device' => 'exact'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['device.device' => 'exact'])]
    private $device;

    public function __construct()
    {
        $this->invoice_date = new \DateTime('now');
        $this->alter_date = new \DateTime('now');
        $this->dueDate = new \DateTime('now');
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
        $this->otherInformations = json_encode(new stdClass());
        $this->portion = 0;
        $this->installments = 0;
        $this->price = 0;
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
     * Add OrderInvoice
     *
     * @param \ControleOnline\Entity\OrderInvoice $order
     * @return People
     */
    public function addOrder(\ControleOnline\Entity\OrderInvoice $order)
    {
        $this->order[] = $order;
        return $this;
    }
    /**
     * Remove OrderInvoice
     *
     * @param \ControleOnline\Entity\OrderInvoice $order
     */
    public function removeOrder(\ControleOnline\Entity\OrderInvoice $order)
    {
        $this->order->removeElement($order);
    }
    /**
     * Get OrderInvoice
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

    /**
     * Get otherInformations
     *
     * @return stdClass
     */
    public function getOtherInformations($decode = false)
    {
        return $decode ? (object) json_decode((is_array($this->otherInformations) ? json_encode($this->otherInformations) : $this->otherInformations)) : $this->otherInformations;
    }

    /**
     * Set comments
     *
     * @param string $otherInformations
     * @return Order
     */
    public function addOtherInformations($key, $value)
    {
        $otherInformations = $this->getOtherInformations(true);
        $otherInformations->$key = $value;
        $this->otherInformations = json_encode($otherInformations);
        return $this;
    }

    /**
     * Set comments
     *
     * @param string $otherInformations
     * @return Order
     */
    public function setOtherInformations($otherInformations)
    {
        $this->otherInformations = json_encode($otherInformations);
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
     * Get the value of portion
     */
    public function getPortion()
    {
        return $this->portion;
    }

    /**
     * Set the value of portion
     */
    public function setPortion($portion): self
    {
        $this->portion = $portion;

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

    /**
     * Get the value of installment_id
     */
    public function getInstallmentId()
    {
        return $this->installment_id;
    }

    /**
     * Set the value of installment_id
     */
    public function setInstallmentId($installment_id): self
    {
        $this->installment_id = $installment_id;

        return $this;
    }

    /**
     * Get the value of sourceWallet
     */
    public function getSourceWallet()
    {
        return $this->sourceWallet;
    }

    /**
     * Set the value of sourceWallet
     */
    public function setSourceWallet($sourceWallet): self
    {
        $this->sourceWallet = $sourceWallet;

        return $this;
    }

    /**
     * Get the value of destinationWallet
     */
    public function getDestinationWallet()
    {
        return $this->destinationWallet;
    }

    /**
     * Set the value of destinationWallet
     */
    public function setDestinationWallet($destinationWallet): self
    {
        $this->destinationWallet = $destinationWallet;

        return $this;
    }

    /**
     * Get the value of user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of device
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * Set the value of device
     */
    public function setDevice($device): self
    {
        $this->device = $device;

        return $this;
    }
}
