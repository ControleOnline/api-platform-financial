<?php

namespace ControleOnline\Entity;

class Invoice
{
    public function getDateAsString(\DateTime $date = null): string
    {
        return ($date !== null ? $date : (new \DateTime))->format('Y-m-d');
    }
}
