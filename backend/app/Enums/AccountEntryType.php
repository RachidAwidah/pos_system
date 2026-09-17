<?php

namespace App\Enums;

enum AccountEntryType: string
{
    case Sale = 'sale';
    case CustomerPayment = 'customer_payment';
    case SalesReturn = 'sales_return';
    case Purchase = 'purchase';
    case SupplierPayment = 'supplier_payment';
    case SupplierReturn = 'supplier_return';
    case Adjustment = 'adjustment';
}
