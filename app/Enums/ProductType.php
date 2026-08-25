<?php

namespace App\Enums;

enum ProductType: string
{
    case Stock = 'stock';
    case NonStock = 'non_stock';
    case Service = 'service';

    public function tracksInventory(): bool
    {
        return $this === self::Stock;
    }
}
