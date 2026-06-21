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
            ->mapWithKeys(fn ($c) => [$c->value => $c->value])
            ->toArray();
    }

    public function label(): string
    {
        return match ($this) {
            self::Blister => 'Blister',
            self::Sealed => 'Sealed',
            self::Tube => 'Tube',
            self::CoinCapsule => 'Coin Capsule',
            self::PresentationCase => 'Presentation Case',
        };
    }
}
