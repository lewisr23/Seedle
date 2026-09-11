<?php

namespace App\Enums;

enum GuideCategory: string
{
    case GettingStarted = 'getting_started';
    case SoilAndFeeding = 'soil_and_feeding';
    case Watering = 'watering';
    case PestControl = 'pest_control';
    case Seasonal = 'seasonal';
    case Tools = 'tools';
    case Composting = 'composting';

    public function label(): string
    {
        return match ($this) {
            self::GettingStarted => 'Getting Started',
            self::SoilAndFeeding => 'Soil & Feeding',
            self::Watering => 'Watering',
            self::PestControl => 'Pest Control',
            self::Seasonal => 'Seasonal',
            self::Tools => 'Tools',
            self::Composting => 'Composting',
        };
    }
}
