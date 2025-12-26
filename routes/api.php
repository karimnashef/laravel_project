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

    // Public auth
    Route::post('register', [AuthController::class, 'store']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refreshToken']);
    Route::post('auth/reset-account', [AuthController::class, 'resetAccount']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function() {

        Route::prefix('user')->group(function() {
            Route::prefix('profile')->group(function() {
                Route::get('/' , [AuthController::class , 'index']);
                Route::put('/update' , [AuthController::class , 'update']);
                Route::post('/soft-delete' , [AuthController::class , 'softDelete']);
                Route::post('/logout' , [AuthController::class , 'logout']);
                Route::post('/switch/{id}' , [AuthController::class , 'switchAccount']);
                Route::delete('/delete' , [AuthController::class , 'hardDelete']);
            });
        });

        Route::prefix('products')->group(function() {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'softDelete']);
            Route::post('/{product}/restore', [ProductController::class, 'restore']);
            Route::delete('/{product}/force', [ProductController::class, 'destroy']);
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

        // Admin user actions
        Route::prefix('admin')->group(function () {
            Route::post('users/{id}/block', [AuthController::class, 'blockUser']);
            Route::post('users/{id}/unblock', [AuthController::class, 'unblockUser']);
        });
    });

});
