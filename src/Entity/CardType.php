<?php

namespace ControleOnline\Entity;

enum CardType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
    case VOUCHER = 'voucher';
    case EMPTY = '';
}
