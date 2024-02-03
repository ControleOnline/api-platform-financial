<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Payment
 * @ORM\EntityListeners({ControleOnline\Listener\LogListener::class})
 * @ORM\Table(name="payment")
 * @ORM\Entity
 */
class Payment
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="period", type="string", length=0, nullable=false, options={"default"="'Unique'"})
     */
    private $period = '\'Unique\'';

    /**
     * @var string
     *
     * @ORM\Column(name="unit", type="string", length=0, nullable=false, options={"default"="'Single'"})
     */
    private $unit = '\'Single\'';


}
