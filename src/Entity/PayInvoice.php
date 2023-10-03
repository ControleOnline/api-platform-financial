<?php

namespace ControleOnline\Entity;

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

/**
 * PayInvoice
 *
 * @ORM\EntityListeners ({App\Listener\LogListener::class})
 * @ORM\Table (name="invoice", indexes={@ORM\Index (name="invoice_subtype", columns={"invoice_subtype"})})
 * @ORM\Entity (repositoryClass="ControleOnline\Repository\PayInvoiceRepository")
 */
#[ApiResource(
    operations: [
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/pay/{id}'
        ), new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/pay/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: \App\Controller\GetBankItauDataAction::class
        )
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice_read']],
    denormalizationContext: ['groups' => ['invoice_write']]
)]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['dueDate' => 'DESC'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['status' => 'exact', 'status.realStatus' => 'exact', 'order.order' => 'exact'])]
class PayInvoice extends Invoice
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"logistic_read"})  
     */
    private $id;
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\ControleOnline\Entity\PurchasingOrderInvoice", mappedBy="invoice", cascade={"persist"})
     * @Groups({"invoice_read"})
     */
    private $order;
    /**
     * @var \App\Entity\Status
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Status")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="status_id", referencedColumnName="id")
     * })
     * @Groups({"invoice_read","logistic_read"})
     */
    private $status;
    /**
     * @var \DateTime
     * @ORM\Column(name="invoice_date", type="datetime",  nullable=false, columnDefinition="DATETIME")
     */
    private $invoice_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="alter_date", type="datetime",  nullable=false, columnDefinition="DATETIME on update CURRENT_TIMESTAMP")
     */
    private $alter_date;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="due_date", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"invoice_read", "invoice_pay_put_edit"})
     * @Assert\NotBlank(groups={"invoice_pay_put_validation"})
     * @Assert\DateTime(groups={"invoice_pay_put_validation"})
     * @Assert\Expression(
     *     "this.getDateAsString(this.getDueDate()) > this.getDateAsString()",
     *     message="Duedate must be greater than today",
     *     groups ={"invoice_pay_put_validation"}
     * )
     */
    private $dueDate;
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="payment_date", type="datetime",  nullable=true, columnDefinition="DATETIME")
     */
    private $payment_date;
    /**
     * @var boolean
     *
     * @ORM\Column(name="notified", type="boolean",  nullable=false)
     * @Groups({"invoice_pay_notified_edit"})
     * @Assert\Type(
     *  type  ="bool",
     *  groups={"invoice_pay_notified_validation"}
     * )
     */
    private $notified;
    /**
     * @var float
     *
     * @ORM\Column(name="price", type="float",  nullable=true)
     * @Groups({"invoice_read"})
     */
    private $price;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice_type", type="string",  nullable=true)
     */
    private $invoiceType;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice_subtype", type="string",  nullable=true)
     */
    private $invoice_subtype;
    /**
     * @var string
     *
     * @ORM\Column(name="payment_response", type="string",  nullable=true)
     */
    private $payment_response;
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\ServiceInvoiceTax", mappedBy="invoice")
     */
    private $service_invoice_tax;
    /**
     * @var \App\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"invoice_read"})
     */
    private $category = null;
    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", nullable=true)
     * @Groups({"invoice_read"})
     */
    private $description = null;
    /**
     * @var string
     *
     * @ORM\Column(name="payment_mode", type="integer", nullable=true)
     * @Groups({"invoice_read"})
     */
    private $paymentMode = null;
    public function __construct()
    {
        $this->invoice_date = new \DateTime('now');
        $this->alter_date = new \DateTime('now');
        $this->dueDate = new \DateTime('now');
        $this->payment_date = new \DateTime('now');
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
        $this->service_invoice_tax = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set invoice_type
     *
     * @param string $invoice_type
     * @return Order
     */
    public function setInvoiceType($invoice_type)
    {
        $this->invoiceType = $invoice_type;
        return $this;
    }
    /**
     * Get invoice_type
     *
     * @return string
     */
    public function getInvoiceType()
    {
        return $this->invoiceType;
    }
    /**
     * Set payment_response
     *
     * @param string $payment_response
     * @return Invoice
     */
    public function setPaymentResponse($payment_response)
    {
        $this->payment_response = $payment_response;
        return $this;
    }
    /**
     * Get payment_response
     *
     * @return string
     */
    public function getPaymentResponse()
    {
        return $this->payment_response;
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
     * Set invoice_subtype
     *
     * @param string $invoice_subtype
     * @return Invoice
     */
    public function setInvoiceSubtype($invoice_subtype)
    {
        $this->invoice_subtype = $invoice_subtype;
        return $this;
    }
    /**
     * Get invoice_subtype
     *
     * @return string
     */
    public function getInvoiceSubtype()
    {
        return $this->invoice_subtype;
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
     * Get payment_date
     *
     * @return \DateTimeInterface
     */
    public function getPaymentDate()
    {
        return $this->payment_date;
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
     * Set price
     *
     * @param \DateTime $payment_date
     * @return Invoice
     */
    public function setPaymentDate($payment_date)
    {
        $this->payment_date = $payment_date;
        return $this;
    }
    /**
     * Set status
     *
     * @param \App\Entity\Status $status
     * @return Order
     */
    public function setStatus(\App\Entity\Status $status = null)
    {
        $this->status = $status;
        return $this;
    }
    /**
     * Get status
     *
     * @return \App\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }
    /**
     * Add service_invoice_tax
     *
     * @param \App\Entity\ServiceInvoiceTax $service_invoice_tax
     * @return Order
     */
    public function addAServiceInvoiceTax(\App\Entity\ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax[] = $service_invoice_tax;
        return $this;
    }
    /**
     * Remove service_invoice_tax
     *
     * @param \App\Entity\ServiceInvoiceTax $service_invoice_tax
     */
    public function removeServiceInvoiceTax(\App\Entity\ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax->removeElement($service_invoice_tax);
    }
    /**
     * Get service_invoice_tax
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getServiceInvoiceTax()
    {
        return $this->service_invoice_tax;
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
    public function setDescription(?string $description = null)
    {
        $this->description = $description;
        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setPaymentMode(?int $mode = null)
    {
        $this->paymentMode = $mode;
        return $this;
    }
    public function getPaymentMode(): ?int
    {
        return $this->paymentMode;
    }
}
