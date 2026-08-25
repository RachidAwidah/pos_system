<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earned = 'earned';
    case Redeemed = 'redeemed';
    case Reversed = 'reversed';
    case Adjustment = 'adjustment';
}
