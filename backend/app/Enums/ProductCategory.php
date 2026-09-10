<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Seed = 'seed';
    case LivePlant = 'live_plant';
    case Tool = 'tool';
    case Fertilizer = 'fertilizer';
    case Other = 'other';
}
