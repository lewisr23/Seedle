<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'title' => 'New message from @'.$this->message->sender->username,
            'body' => Str::limit($this->message->body, 80),
            'actor' => $this->message->sender->username,
            'conversation_id' => $this->message->conversation_id,
            'link' => "/messages/{$this->message->conversation_id}",
        ];
    }
}
