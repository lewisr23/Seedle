<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\GardenBedController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PlantController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public browsing — products, plant reference data, and the explore feed
// don't require an account, same as any real storefront.
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/plants', [PlantController::class, 'index']);
Route::get('/plants/recommendations', [PlantController::class, 'recommendations']);
Route::get('/plants/{plant}', [PlantController::class, 'show']);

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);

Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/followers', [UserController::class, 'followers']);
Route::get('/users/{user}/following', [UserController::class, 'following']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/users/{user}/follow', [UserController::class, 'follow']);
    Route::delete('/users/{user}/follow', [UserController::class, 'unfollow']);

    Route::get('/feed', [PostController::class, 'feed']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    Route::post('/posts/{post}/like', [PostController::class, 'like']);
    Route::delete('/posts/{post}/like', [PostController::class, 'unlike']);
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/sales', [OrderController::class, 'sales']);

    Route::get('/garden-beds', [GardenBedController::class, 'index']);
    Route::post('/garden-beds', [GardenBedController::class, 'store']);
    Route::get('/garden-beds/{gardenBed}', [GardenBedController::class, 'show']);
    Route::delete('/garden-beds/{gardenBed}', [GardenBedController::class, 'destroy']);
    Route::post('/garden-beds/{gardenBed}/plants', [GardenBedController::class, 'addPlant']);
    Route::delete('/garden-beds/{gardenBed}/plants/{entry}', [GardenBedController::class, 'removePlant']);
});
