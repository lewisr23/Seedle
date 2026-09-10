<?php

namespace App\Enums;

enum PostType: string
{
    case Update = 'update';
    case Question = 'question';
    case Tip = 'tip';
}
