<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Want;

class WantPolicy
{
    public function manage(User $user, Want $want): bool
    {
        return $user->id === $want->user_id;
    }
}
