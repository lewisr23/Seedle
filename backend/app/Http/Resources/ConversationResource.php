<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            // Every inbox row is "me and them", so resolve the other party here
            // rather than making the client work out which side it is on.
            'counterpart' => new UserResource($this->counterpartFor($user)),
            'product' => new ProductResource($this->whenLoaded('product')),
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->when(
                isset($this->unread_count),
                fn () => (int) $this->unread_count
            ),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
