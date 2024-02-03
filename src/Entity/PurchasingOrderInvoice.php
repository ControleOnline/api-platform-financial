<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
/**
 * PurchasingOrderInvoice
 *
 * @ORM\EntityListeners ({ControleOnline\Listener\LogListener::class})
 * @ORM\Table (name="order_invoice", uniqueConstraints={@ORM\UniqueConstraint (name="order_id", columns={"order_id", "invoice_id"})}, indexes={@ORM\Index (name="invoice_id", columns={"invoice_id"})})
 * @ORM\Entity
 */
#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')')], formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']], normalizationContext: ['groups' => ['order_invoice_read']], denormalizationContext: ['groups' => ['order_invoice_write']])]
class PurchasingOrderInvoice
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
     * @var \ControleOnline\Entity\PayInvoice
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\PayInvoice", inversedBy="order")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="invoice_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read"})  
     */
    private $invoice;
    /**
     * @var \ControleOnline\Entity\PurchasingOrder
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\PurchasingOrder", inversedBy="invoice")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * })
     * @Groups({"invoice_read"})
     */
    private $order;
    /**
     * @var float
     *
     * @ORM\Column(name="real_price", type="float",  nullable=false)
     * @Groups({"invoice_read"})
     */
    private $real_price = 0;
    public function __construct()
    {
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
        $this->invoice = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set invoice
     *
     * @param \ControleOnline\Entity\PayInvoice $invoice
     * @return PurchasingOrderInvoice
     */
    public function setInvoice(\ControleOnline\Entity\PayInvoice $invoice = null)
    {
        $this->invoice = $invoice;
        return $this;
    }
    /**
     * Get invoice
     *
     * @return \ControleOnline\Entity\PayInvoice
     */
    public function getInvoice()
    {
        return $this->invoice;
    }
    /**
     * Set order
     *
     * @param \ControleOnline\Entity\PurchasingOrder $order
     * @return PurchasingOrderInvoice
     */
    public function setOrder(\ControleOnline\Entity\PurchasingOrder $order = null)
    {
        $this->order = $order;
        return $this;
    }
    /**
     * Get order
     *
     * @return \ControleOnline\Entity\PurchasingOrder
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set real_price
     *
     * @param float $real_price
     * @return Order
     */
    public function setRealPrice($real_price)
    {
        $this->real_price = $real_price;
        return $this;
    }
    /**
     * Get real_price
     *
     * @return float
     */
    public function getRealPrice()
    {
        return $this->real_price;
    }
}
