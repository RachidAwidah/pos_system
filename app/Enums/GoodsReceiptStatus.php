<?php

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
