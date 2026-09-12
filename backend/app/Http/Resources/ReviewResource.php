<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'user' => new UserResource($this->whenLoaded('user')),
            // Listing reviews is a public route, so the default guard sees no
            // user: ask the sanctum guard explicitly to honour a bearer token.
            'is_mine' => $request->user('sanctum')?->id === $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
