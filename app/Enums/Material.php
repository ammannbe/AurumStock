<?php

namespace App\Enums;

enum Material: int
{
    case Gold = 1;
    case Silver = 2;
    case Platinum = 3;
    case Palladium = 4;

    /**
     * Return mapping of material value => provider symbol
     *
     * @return array<int,string>
     */
    public static function symbolMap(bool $uppercase = false): array
    {
        $map = [];

        foreach (self::cases() as $case) {
            $map[$case->value] = $uppercase
                ? strtoupper($case->symbol())
                : $case->symbol();
        }

        return $map;
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Gold => 'Au',
            self::Silver => 'Ag',
            self::Platinum => 'Pt',
            self::Palladium => 'Pd',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Gold => __('Gold'),
            self::Silver => __('Silver'),
            self::Platinum => __('Platinum'),
            self::Palladium => __('Palladium'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Gold => 'gold',
            self::Silver => 'silver',
            self::Platinum => 'platinum',
            self::Palladium => 'palladium',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gold => 'heroicon-m-star',
            self::Silver => 'heroicon-m-circle-stack',
            self::Platinum => 'heroicon-m-sparkles',
            self::Palladium => 'heroicon-m-square-3-stack-3d',
        };
    }
}
