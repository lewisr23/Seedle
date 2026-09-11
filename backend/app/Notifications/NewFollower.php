<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewFollower extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $follower) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_follower',
            'title' => 'New follower',
            'body' => "@{$this->follower->username} started following you",
            'actor' => $this->follower->username,
            'link' => "/u/{$this->follower->username}",
        ];
    }
}
