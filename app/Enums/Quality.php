<?php

namespace App\Enums;

enum Quality: string
{
    case Proof = 'proof';
    case MintState = 'mint_state';
    case ExtremelyFine = 'extremely_fine';
    case VeryFine = 'very_fine';
    case Fine = 'fine';
    case Good = 'good';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Proof => __('Proof'),
            self::MintState => __('Mint State / Uncirculated (MS / UNC)'),
            self::ExtremelyFine => __('Extremely Fine (XF oder EF)'),
            self::VeryFine => __('Very Fine (VF)'),
            self::Fine => __('Fine (F)'),
            self::Good => __('Good (G)'),
            self::Poor => __('Poor (P)'),
        };
    }
}
