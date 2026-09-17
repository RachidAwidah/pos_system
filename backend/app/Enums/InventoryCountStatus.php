<?php

namespace App\Enums;

enum InventoryCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Reviewed = 'reviewed';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
}
