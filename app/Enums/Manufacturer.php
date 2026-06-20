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

    public function label(): string
    {
        return match ($this) {
            self::Swissmint => 'Swissmint',
            self::PerthMint => 'Perth Mint',
            self::RoyalMint => 'Royal Mint',
            self::PampSuisse => 'PAMP Suisse',
            self::ArgorHeraeus => 'Argor-Heraeus',
            self::Valcambi => 'Valcambi',
            self::MuenzeOesterreich => 'Münze Österreich',
            self::BayerischesHauptmuenzamt => 'Bayerisches Hauptmünzamt',
            self::HamburgerMuenze => 'Hamburger Münze',
            self::StaatlicheMuenzenBW => 'Staatliche Münzen Baden-Württemberg',
            self::MonnaieDeParis => 'Monnaie de Paris',
            self::RoyalCanadianMint => 'Royal Canadian Mint',
            self::UnitedStatesMint => 'United States Mint',
            self::SouthAfricanMint => 'South African Mint',
            self::RandRefinery => 'Rand Refinery',
            self::ChinaMint => 'China Mint',
            self::JohnsonMatthey => 'Johnson Matthey',
            self::Engelhard => 'Engelhard',
        };
    }
}
