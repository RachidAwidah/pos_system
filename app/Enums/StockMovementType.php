<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Sale = 'sale';
    case Purchase = 'purchase';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
