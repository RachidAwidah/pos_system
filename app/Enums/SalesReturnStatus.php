<?php

namespace App\Enums;

enum SalesReturnStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
