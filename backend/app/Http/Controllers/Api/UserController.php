<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function show(Request $request, User $user): UserResource
    {
        $user->loadCount('followers', 'following');

        return new UserResource($user);
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        if ($follower->is($user)) {
            return response()->json(['message' => "You can't follow yourself."], 422);
        }

        $follower->following()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => "Now following {$user->username}."]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $request->user()->following()->detach($user->id);

        return response()->json(['message' => "Unfollowed {$user->username}."]);
    }

    public function followers(User $user): AnonymousResourceCollection
    {
        return UserResource::collection($user->followers()->paginate(20));
    }

    public function following(User $user): AnonymousResourceCollection
    {
        return UserResource::collection($user->following()->paginate(20));
    }
}
