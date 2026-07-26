<?php

use App\Http\Controllers\ShippingProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shipping-provider', ShippingProviderWebhookController::class)
    ->middleware('throttle:shipping-provider-webhooks')
    ->name('webhooks.shipping-provider');

Route::get('/', function () {
    return view('welcome');
});
