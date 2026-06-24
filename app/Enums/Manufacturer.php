<?php

namespace App\Enums;

enum Manufacturer: string
{
    case Swissmint = 'swissmint';
    case PerthMint = 'perth_mint';
    case RoyalMint = 'royal_mint';
    case PampSuisse = 'pamp_suisse';
    case ArgorHeraeus = 'argor_heraeus';
    case Valcambi = 'valcambi';
    case MuenzeOesterreich = 'muenze_oesterreich';
    case BayerischesHauptmuenzamt = 'bayerisches_hauptmuenzamt';
    case HamburgerMuenze = 'hamburger_muenze';
    case StaatlicheMuenzenBW = 'staatliche_muenzen_bw';
    case MonnaieDeParis = 'monnaie_de_paris';
    case RoyalCanadianMint = 'royal_canadian_mint';
    case UnitedStatesMint = 'united_states_mint';
    case SouthAfricanMint = 'south_african_mint';
    case RandRefinery = 'rand_refinery';
    case ChinaMint = 'china_mint';
    case JohnsonMatthey = 'johnson_matthey';
    case Engelhard = 'engelhard';
    case Metalor = 'metalor';

    public function label(): string
    {
        return match ($this) {
            self::Swissmint => __('Swissmint'),
            self::PerthMint => __('Perth Mint'),
            self::RoyalMint => __('Royal Mint'),
            self::PampSuisse => __('PAMP Suisse'),
            self::ArgorHeraeus => __('Argor-Heraeus'),
            self::Valcambi => __('Valcambi'),
            self::MuenzeOesterreich => __('Münze Österreich'),
            self::BayerischesHauptmuenzamt => __('Bayerisches Hauptmünzamt'),
            self::HamburgerMuenze => __('Hamburger Münze'),
            self::StaatlicheMuenzenBW => __('Staatliche Münzen Baden-Württemberg'),
            self::MonnaieDeParis => __('Monnaie de Paris'),
            self::RoyalCanadianMint => __('Royal Canadian Mint'),
            self::UnitedStatesMint => __('United States Mint'),
            self::SouthAfricanMint => __('South African Mint'),
            self::RandRefinery => __('Rand Refinery'),
            self::ChinaMint => __('China Mint'),
            self::JohnsonMatthey => __('Johnson Matthey'),
            self::Engelhard => __('Engelhard'),
            self::Metalor => __('Metalor'),
        };
    }
}
