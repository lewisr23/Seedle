<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    private function baseQuery()
    {
        return Post::query()
            ->with(['user', 'plant', 'likes:id,post_id,user_id'])
            ->withCount(['likes', 'comments']);
    }

    /**
     * Posts from people the current user follows, plus their own.
     */
    public function feed(Request $request): AnonymousResourceCollection
    {
        $followingIds = $request->user()->following()->pluck('users.id');
        $ids = $followingIds->push($request->user()->id);

        $posts = $this->baseQuery()
            ->whereIn('user_id', $ids)
            ->latest()
            ->paginate(20);

        return PostResource::collection($posts);
    }

    /**
     * Public explore feed, everyone's posts, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->baseQuery();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $posts = $query->latest()->paginate(20);

        return PostResource::collection($posts);
    }

    /**
     * A single user's posts, pinned post(s) first, used on profile pages.
     */
    public function forUser(User $user): AnonymousResourceCollection
    {
        $posts = $this->baseQuery()
            ->where('user_id', $user->id)
            ->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->latest()
            ->paginate(20);

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource(
            $post->load(['user', 'plant', 'likes:id,post_id,user_id'])->loadCount(['likes', 'comments'])
        );
    }

    public function store(StorePostRequest $request): PostResource
    {
        $post = $request->user()->posts()->create($request->validated());

        return new PostResource($post->load(['user', 'plant']));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    public function like(Request $request, Post $post): JsonResponse
    {
        $post->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['message' => 'Liked.']);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $post->likes()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Unliked.']);
    }

    public function pin(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post->update(['pinned_at' => now()]);

        return response()->json(['message' => 'Pinned to your profile.']);
    }

    public function unpin(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post->update(['pinned_at' => null]);

        return response()->json(['message' => 'Unpinned.']);
    }
}
