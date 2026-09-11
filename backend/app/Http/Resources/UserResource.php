<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'location' => $this->location,
            'hardiness_zone' => $this->hardiness_zone,
            'avatar_path' => $this->avatar_path,
            'followers_count' => $this->whenCounted('followers'),
            'following_count' => $this->whenCounted('following'),
            'products_count' => $this->whenCounted('products'),
            'is_following' => $this->when(
                $request->user() && $request->user()->isNot($this->resource),
                fn () => $request->user()->isFollowing($this->resource)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
