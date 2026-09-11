<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'body' => $this->body,
            'image_path' => $this->image_path,
            'user' => new UserResource($this->whenLoaded('user')),
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'pinned' => $this->pinned_at !== null,
            'likes_count' => $this->whenCounted('likes'),
            'comments_count' => $this->whenCounted('comments'),
            'liked_by_me' => $this->when(
                $request->user() !== null,
                fn () => $this->likes->contains('user_id', $request->user()?->id)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
