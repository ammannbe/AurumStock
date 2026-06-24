<?php

namespace App\Enums;

enum SharePermission: int
{
    case View = 1;
    case Edit = 2;

    public function label(): string
    {
        return match ($this) {
            self::View => __('View'),
            self::Edit => __('Edit'),
        };
    }
}
