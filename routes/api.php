<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StockCardController;
use App\Http\Controllers\API\StockMutationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rute publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute yang memerlukan autentikasi
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    // Products - akses untuk semua
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    
    // Products - akses hanya admin
    Route::middleware('admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });
    
    // Stock Cards - akses hanya admin
    Route::middleware('admin')->group(function () {
        Route::get('/stock-cards', [StockCardController::class, 'index']);
        Route::post('/stock-cards', [StockCardController::class, 'store']);
        Route::get('/stock-cards/{id}', [StockCardController::class, 'show']);
        Route::put('/stock-cards/{id}', [StockCardController::class, 'update']);
        Route::delete('/stock-cards/{id}', [StockCardController::class, 'destroy']);
        Route::get('/products/{productId}/stock-cards', [StockCardController::class, 'getByProduct']);
    });
    
    // Stock Mutations - akses hanya admin
    Route::middleware('admin')->group(function () {
        Route::get('/stock-mutations', [StockMutationController::class, 'index']);
        Route::post('/stock-mutations', [StockMutationController::class, 'store']);
        Route::get('/stock-mutations/{id}', [StockMutationController::class, 'show']);
        Route::get('/products/{productId}/stock-mutations', [StockMutationController::class, 'getByProduct']);
    });
    
    // Orders - akses user
    Route::get('/orders/my-orders', [OrderController::class, 'getMyOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    
    // Orders - akses admin
    Route::middleware('admin')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
});
