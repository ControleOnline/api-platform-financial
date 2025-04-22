<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ControleOnline\Controller\GetBankInterDataAction;
use ControleOnline\Controller\GetBankItauDataAction;
use ControleOnline\Controller\PaylistController;
use ControleOnline\Controller\SplitInvoiceAction;
use ControleOnline\DataProvider\InvoiceDataProvider;
use ControleOnline\Listener\LogListener;
use ControleOnline\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use DateTime;
use DateTimeInterface;
use stdClass;

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
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/invoice/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: GetBankItauDataAction::class
        ),
        new GetCollection(
            uriTemplate: '/paylist',
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            controller: PaylistController::class,
            description: 'Retrieve invoices based on document and company.'
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')',
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
            security: 'is_granted(\'ROLE_ADMIN\') or (is_granted(\'ROLE_CLIENT\'))',
            validationContext: ['groups' => ['invoice:write']],
            denormalizationContext: ['groups' => ['invoice:write']]
        ),
        new Get(
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            uriTemplate: '/finance/{id}/download',
            requirements: ['id' => '[\\w-]+'],
            controller: GetBankInterDataAction::class
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/receive/{id}/bank/itau/{operation}',
            requirements: ['operation' => '^(itauhash|payment)+$'],
            controller: GetBankItauDataAction::class
        ),
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/finance/receive/{id}/bank/inter/{operation}',
            requirements: ['operation' => '^(download|payment)+$'],
            controller: GetBankInterDataAction::class
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
            controller: SplitInvoiceAction::class
        ),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']]
)]
#[ORM\Table(name: 'invoice')]
#[ORM\EntityListeners([LogListener::class])]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read'])]
    private $id;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['order.order' => 'exact'])]
    #[ORM\OneToMany(targetEntity: OrderInvoice::class, mappedBy: 'invoice')]
    private $order;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['status' => 'exact', 'status.realStatus' => 'exact'])]
    #[ORM\JoinColumn(name: 'status_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $status;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['payer' => 'exact'])]
    #[ORM\JoinColumn(name: 'payer_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $payer;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['receiver' => 'exact'])]
    #[ORM\JoinColumn(name: 'receiver_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $receiver;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['invoice_date' => 'exact'])]
    #[ORM\Column(name: 'invoice_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $invoice_date;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['alter_date' => 'exact'])]
    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME on update CURRENT_TIMESTAMP')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $alter_date;

    #[ApiFilter(OrderFilter::class, properties: ['dueDate' => 'DESC', 'id' => 'DESC'])]
    #[ApiFilter(filterClass: DateFilter::class, properties: ['dueDate'])]
    #[ORM\Column(name: 'due_date', type: 'datetime', nullable: false, columnDefinition: 'DATE')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $dueDate;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['notified' => 'exact'])]
    #[ORM\Column(name: 'notified', type: 'boolean', nullable: false)]
    #[Assert\Type(type: 'bool')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write', 'invoice_pay_notified_edit'])]
    private $notified = false;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['price' => 'exact'])]
    #[ORM\Column(name: 'price', type: 'float', nullable: true)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $price;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category' => 'exact'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $category = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $otherInformations;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['sourceWallet' => 'exact'])]
    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $sourceWallet;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['destinationWallet' => 'exact'])]
    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $destinationWallet;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['paymentType' => 'exact'])]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $paymentType;

    #[ORM\Column(name: 'portion_number', type: 'integer', nullable: false)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $portion;

    #[ORM\Column(name: 'installments', type: 'integer', nullable: false)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $installments;

    #[ORM\Column(name: 'installment_id', type: 'integer', nullable: true)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $installment_id;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['user' => 'exact'])]
    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['invoice:read', 'invoice_details:read', 'logistic:read', 'invoice:write', 'order_invoice:write'])]
    private $user;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['device' => 'exact', 'device.device' => 'exact'])]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[Groups(['device_config:read', 'device:read', 'device_config:write'])]
    private $device;

    public function __construct()
    {
        $this->invoice_date = new DateTime('now');
        $this->alter_date = new DateTime('now');
        $this->dueDate = new DateTime('now');
        $this->order = new ArrayCollection();
        $this->otherInformations = json_encode(new stdClass());
        $this->portion = 0;
        $this->installments = 0;
        $this->price = 0;
    }

    public function getId()
    {
        return $this->id;
    }

    public function addOrder(OrderInvoice $order)
    {
        $this->order[] = $order;
        return $this;
    }

    public function removeOrder(OrderInvoice $order)
    {
        $this->order->removeElement($order);
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setPrice($price)
    {
        $this->price = $price;
        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getInvoiceDate()
    {
        return $this->invoice_date;
    }

    public function getAlterDate()
    {
        return $this->alter_date;
    }

    public function getDueDate()
    {
        return $this->dueDate;
    }

    public function setDueDate(DateTime $due_date)
    {
        $this->dueDate = $due_date;
        return $this;
    }

    public function setStatus(Status $status = null)
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setNotified($notified)
    {
        $this->notified = $notified;
        return $this;
    }

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

    public function getPayer()
    {
        return $this->payer;
    }

    public function setPayer($payer): self
    {
        $this->payer = $payer;
        return $this;
    }

    public function getReceiver()
    {
        return $this->receiver;
    }

    public function setReceiver($receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getDateAsString(DateTime $date = null): string
    {
        return ($date ?? new DateTime)->format('Y-m-d');
    }

    public function getOtherInformations($decode = false)
    {
        return $decode ? (object) json_decode((is_array($this->otherInformations) ? json_encode($this->otherInformations) : $this->otherInformations)) : $this->otherInformations;
    }

    public function addOtherInformations($key, $value)
    {
        $otherInformations = $this->getOtherInformations(true);
        $otherInformations->$key = $value;
        $this->otherInformations = json_encode($otherInformations);
        return $this;
    }

    public function setOtherInformations($otherInformations)
    {
        $this->otherInformations = json_encode($otherInformations);
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

    public function getPortion()
    {
        return $this->portion;
    }

    public function setPortion($portion): self
    {
        $this->portion = $portion;
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

    public function getInstallmentId()
    {
        return $this->installment_id;
    }

    public function setInstallmentId($installment_id): self
    {
        $this->installment_id = $installment_id;
        return $this;
    }

    public function getSourceWallet()
    {
        return $this->sourceWallet;
    }

    public function setSourceWallet($sourceWallet): self
    {
        $this->sourceWallet = $sourceWallet;
        return $this;
    }

    public function getDestinationWallet()
    {
        return $this->destinationWallet;
    }

    public function setDestinationWallet($destinationWallet): self
    {
        $this->destinationWallet = $destinationWallet;
        return $this;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDevice()
    {
        return $this->device;
    }

    public function setDevice($device): self
    {
        $this->device = $device;
        return $this;
    }
}
