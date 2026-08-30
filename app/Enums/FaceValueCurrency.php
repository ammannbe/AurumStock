<?php

namespace App\Enums;

/**
 * Nominal currencies a coin can be denominated in. Wider than Currency,
 * which only covers the currencies the app converts between.
 */
enum FaceValueCurrency: string
{
    case AUD = 'AUD';
    case CAD = 'CAD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case MXN = 'MXN';
    case MNT = 'MNT';
    case NZD = 'NZD';
    case RUB = 'RUB';
    case USD = 'USD';
    case ZAR = 'ZAR';

    public function label(): string
    {
        return $this->value.' — '.__($this->currencyName());
    }

    public function currencyName(): string
    {
        return match ($this) {
            self::AUD => 'Australian Dollar',
            self::CAD => 'Canadian Dollar',
            self::CHF => 'Swiss Franc',
            self::CNY => 'Chinese Yuan',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
            self::MXN => 'Mexican Peso',
            self::MNT => 'Mongolian Tugrik',
            self::NZD => 'New Zealand Dollar',
            self::RUB => 'Russian Ruble',
            self::USD => 'US Dollar',
            self::ZAR => 'South African Rand',
        };
    }

    /**
     * Best guess for a country, used to prefill the field.
     */
    public static function forCountry(?string $country): ?self
    {
        return match ($country) {
            'AU' => self::AUD,
            'AT', 'DE', 'FR' => self::EUR,
            'CA' => self::CAD,
            'CH' => self::CHF,
            'CK', 'NU' => self::NZD,
            'CN' => self::CNY,
            'GB' => self::GBP,
            'MN' => self::MNT,
            'MX' => self::MXN,
            'RU' => self::RUB,
            'US' => self::USD,
            'ZA' => self::ZAR,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->toArray();
    }
}
