<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Notifications\NewMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user)
            ->with(['buyer', 'seller', 'product', 'latestMessage.sender'])
            // One subquery for every row's unread count beats one query per row.
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Message::whereHas('conversation', fn ($q) => $q->forUser($user))
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);

        $conversation->load(['buyer', 'seller', 'product', 'messages.sender']);

        // Opening a thread is what "reading" means here.
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return new ConversationResource($conversation);
    }

    /**
     * Start (or reopen) the conversation about a listing. Buyers message
     * sellers, so the listing owner is always the seller side.
     */
    public function store(StoreConversationRequest $request): ConversationResource
    {
        $user = $request->user();
        $product = Product::findOrFail($request->validated()['product_id']);

        if ($product->seller_id === $user->id) {
            throw ValidationException::withMessages([
                'product_id' => 'You cannot start a conversation with yourself about your own listing.',
            ]);
        }

        $conversation = DB::transaction(function () use ($user, $product, $request) {
            // firstOrCreate rather than create: messaging the same seller about
            // the same listing twice should continue the thread, not fork it.
            $conversation = Conversation::firstOrCreate([
                'buyer_id' => $user->id,
                'seller_id' => $product->seller_id,
                'product_id' => $product->id,
            ]);

            $this->appendMessage($conversation, $user->id, $request->validated()['body']);

            return $conversation;
        });

        return new ConversationResource(
            $conversation->load(['buyer', 'seller', 'product', 'messages.sender'])
        );
    }

    public function storeMessage(
        StoreMessageRequest $request,
        Conversation $conversation
    ): MessageResource {
        $this->authorize('reply', $conversation);

        $message = DB::transaction(fn () => $this->appendMessage(
            $conversation,
            $request->user()->id,
            $request->validated()['body']
        ));

        return new MessageResource($message->load('sender'));
    }

    /**
     * Write the message, bump the conversation's sort key, and tell the other
     * side — the notification is queued, so the request doesn't wait on it.
     */
    private function appendMessage(Conversation $conversation, int $senderId, string $body): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $senderId,
            'body' => $body,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        $sender = $senderId === $conversation->buyer_id ? $conversation->buyer : $conversation->seller;
        $conversation->counterpartFor($sender)->notify(new NewMessage($message->load('sender')));

        return $message;
    }
}
