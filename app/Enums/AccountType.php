<?php

namespace App\Enums;

enum AccountType: string
{
    use EnumTrait;

    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
    case BOTH = 'both';
    public function label(): string
    {
        return match ($this) {
            static::CUSTOMER => 'Customer',
            static::SUPPLIER => 'Supplier',
            static::BOTH => 'Both'
        };
    }
}
