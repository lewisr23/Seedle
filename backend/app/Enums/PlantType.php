<?php

namespace App\Enums;

enum PlantType: string
{
    case Vegetable = 'vegetable';
    case Fruit = 'fruit';
    case Herb = 'herb';
    case Flower = 'flower';
    case Tree = 'tree';
    case Shrub = 'shrub';
}
