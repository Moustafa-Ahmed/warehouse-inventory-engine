<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\ShippingProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shipping-provider', ShippingProviderWebhookController::class)
    ->middleware('throttle:shipping-provider-webhooks')
    ->name('webhooks.shipping-provider');

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'can:operate'])->group(function (): void {
    Route::view('/', 'operations.home')->name('operations.home');
    Route::get('/inventory/receipts/create', [InventoryReceiptController::class, 'create'])
        ->name('inventory.receipts.create');
    Route::post('/inventory/receipts', [InventoryReceiptController::class, 'store'])
        ->name('inventory.receipts.store');
});
