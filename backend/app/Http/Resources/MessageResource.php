<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'is_mine' => $request->user()?->id === $this->sender_id,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
