<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'title' => 'New comment on your post',
            'body' => '@'.$this->comment->user->username.': '.Str::limit($this->comment->body, 80),
            'actor' => $this->comment->user->username,
            'post_id' => $this->comment->post_id,
            'link' => "/posts/{$this->comment->post_id}",
        ];
    }
}
