<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
/**
 * PurchasingOrderInvoiceTax
 *
 * @ORM\EntityListeners ({ControleOnline\Listener\LogListener::class})
 * @ORM\Table (name="order_invoice_tax", uniqueConstraints={@ORM\UniqueConstraint (name="order_id", columns={"order_id", "invoice_tax_id"}),@ORM\UniqueConstraint(name="invoice_type", columns={"issuer_id", "invoice_type", "order_id"})}, indexes={@ORM\Index (name="invoice_tax_id", columns={"invoice_tax_id"})})
 * @ORM\Entity
 */
#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')')], formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']], normalizationContext: ['groups' => ['order_invoice_tax_read']], denormalizationContext: ['groups' => ['order_invoice_tax_write']])]
class PurchasingOrderInvoiceTax
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    /**
     * @var \App\Entity\PurchasingInvoiceTax
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\PurchasingInvoiceTax", inversedBy="order")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="invoice_tax_id", referencedColumnName="id")
     * })
     * @Groups({"order_read"})
     */
    private $invoiceTax;
    /**
     * @var \ControleOnline\Entity\PurchasingOrder
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\PurchasingOrder", inversedBy="invoiceTax")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * })
     */
    private $order;
    /**
     * @var \App\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="issuer_id", referencedColumnName="id")
     * })
     */
    private $issuer;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice_type", type="integer",  nullable=false)
     */
    private $invoiceType;
    public function __construct()
    {
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
        $this->invoiceTax = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set invoiceTax
     *
     * @param \App\Entity\PurchasingInvoiceTax $invoice_tax
     * @return PurchasingOrderInvoiceTax
     */
    public function setInvoiceTax(\App\Entity\PurchasingInvoiceTax $invoice_tax = null)
    {
        $this->invoiceTax = $invoice_tax;
        return $this;
    }
    /**
     * Get invoiceTax
     *
     * @return \App\Entity\PurchasingInvoiceTax
     */
    public function getInvoiceTax()
    {
        return $this->invoiceTax;
    }
    /**
     * Set order
     *
     * @param \ControleOnline\Entity\PurchasingOrder $order
     * @return PurchasingOrderInvoiceTax
     */
    public function setOrder(\ControleOnline\Entity\PurchasingOrder $order = null)
    {
        $this->order = $order;
        return $this;
    }
    /**
     * Get order
     *
     * @return \App\Entity\Order
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set invoice_type
     *
     * @param integer $invoice_type
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
     * @return integer
     */
    public function getInvoiceType()
    {
        return $this->invoiceType;
    }
    /**
     * Set issuer
     *
     * @param \App\Entity\People $issuer
     * @return People
     */
    public function setIssuer(\App\Entity\People $issuer = null)
    {
        $this->issuer = $issuer;
        return $this;
    }
    /**
     * Get issuer
     *
     * @return \App\Entity\People
     */
    public function getIssuer()
    {
        return $this->issuer;
    }
}