<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\GardenBedController;
use App\Http\Controllers\Api\GuideController;
use App\Http\Controllers\Api\HarvestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PlantController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedItemController;
use App\Http\Controllers\Api\SowingCalendarController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WantController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public browsing: products, plant reference data, and the explore feed
// don't require an account, same as any real storefront.
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);

Route::get('/images/{path}', [ProductImageController::class, 'show'])->where('path', '.*');

Route::get('/plants', [PlantController::class, 'index']);
Route::get('/plants/recommendations', [PlantController::class, 'recommendations']);
Route::get('/plants/{plant}', [PlantController::class, 'show']);

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);

Route::get('/wants', [WantController::class, 'index']);

Route::get('/sowing-calendar', [SowingCalendarController::class, 'index']);

Route::get('/guides', [GuideController::class, 'index']);
Route::get('/guides/{guide}', [GuideController::class, 'show']);

Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/followers', [UserController::class, 'followers']);
Route::get('/users/{user}/following', [UserController::class, 'following']);
Route::get('/users/{user}/posts', [PostController::class, 'forUser']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::patch('/me', [UserController::class, 'update']);
    Route::get('/suggested-gardeners', [UserController::class, 'suggestions']);
    Route::post('/users/{user}/follow', [UserController::class, 'follow']);
    Route::delete('/users/{user}/follow', [UserController::class, 'unfollow']);

    Route::get('/feed', [PostController::class, 'feed']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    Route::post('/posts/{post}/like', [PostController::class, 'like']);
    Route::delete('/posts/{post}/like', [PostController::class, 'unlike']);
    Route::post('/posts/{post}/pin', [PostController::class, 'pin']);
    Route::delete('/posts/{post}/pin', [PostController::class, 'unpin']);
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);

    Route::get('/saved', [SavedItemController::class, 'index']);
    Route::get('/saved/ids', [SavedItemController::class, 'ids']);
    Route::post('/products/{product}/save', [SavedItemController::class, 'saveProduct']);
    Route::delete('/products/{product}/save', [SavedItemController::class, 'unsaveProduct']);
    Route::post('/plants/{plant}/save', [SavedItemController::class, 'savePlant']);
    Route::delete('/plants/{plant}/save', [SavedItemController::class, 'unsavePlant']);

    Route::post('/wants', [WantController::class, 'store']);
    Route::patch('/wants/{want}/close', [WantController::class, 'close']);
    Route::delete('/wants/{want}', [WantController::class, 'destroy']);

    Route::get('/my-listings', [ProductController::class, 'mine']);
    Route::post('/product-images', [ProductImageController::class, 'store']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::delete('/products/{product}/reviews/{review}', [ReviewController::class, 'destroy']);

    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/sales', [OrderController::class, 'sales']);

    Route::get('/garden-beds', [GardenBedController::class, 'index']);
    Route::post('/garden-beds', [GardenBedController::class, 'store']);
    Route::get('/garden-beds/{gardenBed}', [GardenBedController::class, 'show']);
    Route::put('/garden-beds/{gardenBed}', [GardenBedController::class, 'update']);
    Route::delete('/garden-beds/{gardenBed}', [GardenBedController::class, 'destroy']);
    Route::post('/garden-beds/{gardenBed}/plants', [GardenBedController::class, 'addPlant']);
    Route::patch('/garden-beds/{gardenBed}/plants/{entry}', [GardenBedController::class, 'updatePlant']);
    Route::delete('/garden-beds/{gardenBed}/plants/{entry}', [GardenBedController::class, 'removePlant']);

    Route::get('/harvests', [HarvestController::class, 'index']);
    Route::get('/harvests/summary', [HarvestController::class, 'summary']);
    Route::post('/garden-beds/{gardenBed}/plants/{entry}/harvests', [HarvestController::class, 'store']);
    Route::delete('/harvests/{harvest}', [HarvestController::class, 'destroy']);
});
