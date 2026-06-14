<?php

namespace App\Enums;

enum Material: int
{
    case Gold = 1;
    case Silver = 2;
    case Platinum = 3;
    case Palladium = 4;

    public function symbol(): string
    {
        return match ($this) {
            self::Gold => 'XAU',
            self::Silver => 'XAG',
            self::Platinum => 'XPT',
            self::Palladium => 'XPD',
        };
    }

    /**
     * Return mapping of material value => provider symbol
     *
     * @return array<int,string>
     */
    public static function symbolMap(): array
    {
        $map = [];

        foreach (self::cases() as $case) {
            $map[$case->value] = $case->symbol();
        }

        return $map;
    }
}
