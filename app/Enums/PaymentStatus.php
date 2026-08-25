<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
