<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    /** Replying and marking as read are the same privilege as reading it. */
    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }
}
