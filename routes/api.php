<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\CategoryController;


Route::prefix('v1')->group(function() {

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function() {

        Route::prefix('products')->group(function() {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
        });

        Route::prefix('categories')->group(function() {
            Route::get('/', [CategoryController::class, 'index']);
            Route::get('/{category}', [CategoryController::class, 'show']);
        });

        Route::prefix('cart')->group(function() {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/add', [CartController::class, 'add']);
            Route::post('/remove', [CartController::class, 'remove']);
            Route::post('/clear', [CartController::class, 'clear']);
        });

        Route::prefix('favorites')->group(function() {
            Route::get('/', [FavoriteController::class, 'index']);
            Route::post('/toggle/{product_id}', [FavoriteController::class, 'toggle']);
        });
    });

});
