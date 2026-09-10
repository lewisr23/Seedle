<?php

namespace App\Policies;

use App\Models\GardenBed;
use App\Models\User;

class GardenBedPolicy
{
    public function view(User $user, GardenBed $bed): bool
    {
        return $user->id === $bed->user_id;
    }

    public function update(User $user, GardenBed $bed): bool
    {
        return $user->id === $bed->user_id;
    }

    public function delete(User $user, GardenBed $bed): bool
    {
        return $user->id === $bed->user_id;
    }
}
