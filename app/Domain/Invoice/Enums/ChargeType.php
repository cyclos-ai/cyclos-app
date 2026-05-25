<?php

namespace App\Domain\Invoice\Enums;

enum ChargeType: string
{
    // Ocean charges
    case OCEAN_FREIGHT = 'OCEAN_FREIGHT';
    case BUNKER = 'BUNKER';
    case BAF = 'BAF';
    case CAF = 'CAF';
    case THC = 'THC';
    case DOC_FEE = 'DOC_FEE';
    case BILL_OF_LADING = 'BILL_OF_LADING';
    case ISPS = 'ISPS';
    case AMS = 'AMS';
    case DEMURRAGE = 'DEMURRAGE';
    case DETENTION = 'DETENTION';
    case PER_DIEM = 'PER_DIEM';
    case CHASSIS = 'CHASSIS';
    case STORAGE = 'STORAGE';
    case EXAM_FEE = 'EXAM_FEE';
    case CLEANING = 'CLEANING';
    case SEAL = 'SEAL';
    // Drayage charges
    case DRAY = 'DRAY';
    case FUEL_SURCHARGE = 'FUEL_SURCHARGE';
    case TOLL = 'TOLL';
    case WAIT_TIME = 'WAIT_TIME';
    case OVERWEIGHT = 'OVERWEIGHT';
    case HAZMAT = 'HAZMAT';
    case PREPULL = 'PREPULL';
    case REDELIVERY = 'REDELIVERY';
    case STOP_OFF = 'STOP_OFF';
    // General
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match($this) {
            self::OCEAN_FREIGHT => 'Ocean Freight',
            self::BUNKER => 'Bunker',
            self::BAF => 'BAF',
            self::CAF => 'CAF',
            self::THC => 'THC',
            self::DOC_FEE => 'Documentation Fee',
            self::BILL_OF_LADING => 'Bill of Lading',
            self::ISPS => 'ISPS',
            self::AMS => 'AMS',
            self::DEMURRAGE => 'Demurrage',
            self::DETENTION => 'Detention',
            self::PER_DIEM => 'Per Diem',
            self::CHASSIS => 'Chassis',
            self::STORAGE => 'Storage',
            self::EXAM_FEE => 'Exam Fee',
            self::CLEANING => 'Cleaning',
            self::SEAL => 'Seal',
            self::DRAY => 'Drayage',
            self::FUEL_SURCHARGE => 'Fuel Surcharge',
            self::TOLL => 'Toll',
            self::WAIT_TIME => 'Wait Time',
            self::OVERWEIGHT => 'Overweight',
            self::HAZMAT => 'Hazmat',
            self::PREPULL => 'Pre-Pull',
            self::REDELIVERY => 'Redelivery',
            self::STOP_OFF => 'Stop Off',
            self::OTHER => 'Other',
        };
    }
}
