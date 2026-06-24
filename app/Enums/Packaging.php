<?php

namespace App\Enums;

enum Packaging: string
{
    case Blister = 'blister';
    case Sealed = 'sealed';
    case Tube = 'tube';
    case CoinCapsule = 'coin_capsule';
    case PresentationCase = 'presentation_case';

    /**
     * @return array<int|string, string>
     */
    public static function toArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }

    public function label(): string
    {
        return match ($this) {
            self::Blister => __('Blister'),
            self::Sealed => __('Sealed'),
            self::Tube => __('Tube'),
            self::CoinCapsule => __('Coin Capsule'),
            self::PresentationCase => __('Presentation Case'),
        };
    }
}
