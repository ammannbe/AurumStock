<?php

namespace App\Enums;

enum Quality: string
{
    case Proof = 'proof';
    case Standard = 'standard';

    public function label(): string
    {
        return match ($this) {
            self::Proof => 'Proof',
            self::Standard => 'Standard',
        };
    }
}
