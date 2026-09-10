<?php

namespace App\Enums;

enum SunRequirement: string
{
    case FullSun = 'full_sun';
    case PartialSun = 'partial_sun';
    case Shade = 'shade';
}
