<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'kind', 'subject_id', 'period'])]
class GardenReminder extends Model
{
    public const SOW = 'sow';

    public const HARVEST = 'harvest';
}
