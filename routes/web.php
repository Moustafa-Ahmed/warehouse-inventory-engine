<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FulfillmentController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryBalanceController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\MockProviderControlController;
use App\Http\Controllers\OperationalDashboardController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProviderWebhookReceiptController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingProviderWebhookController;
use App\Http\Controllers\WarehouseController;
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
    Route::get('/', OperationalDashboardController::class)
        ->name('operations.home');
    Route::resource('products', ProductController::class)
        ->except(['show', 'destroy']);
    Route::resource('warehouses', WarehouseController::class)
        ->except(['show', 'destroy']);
    Route::get('/inventory/balances', [InventoryBalanceController::class, 'index'])
        ->name('inventory.balances.index');
    Route::get('/inventory/balances/{inventoryBalance}', [InventoryBalanceController::class, 'show'])
        ->name('inventory.balances.show');
    Route::post('/inventory/balances/{inventoryBalance}/adjustments', InventoryAdjustmentController::class)
        ->name('inventory.adjustments.store');
    Route::post('/inventory/balances/{inventoryBalance}/transfers', InventoryTransferController::class)
        ->name('inventory.transfers.store');
    Route::get('/inventory/receipts/create', [InventoryReceiptController::class, 'create'])
        ->name('inventory.receipts.create');
    Route::post('/inventory/receipts', [InventoryReceiptController::class, 'store'])
        ->name('inventory.receipts.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/items/{item}', [OrderItemController::class, 'update'])
        ->scopeBindings()
        ->name('orders.items.update');
    Route::post('/orders/{order}/items/{item}/reservations', [ReservationController::class, 'store'])
        ->scopeBindings()
        ->name('orders.items.reservations.store');

    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])
        ->name('reservations.show');
    Route::post('/reservations/{reservation}/confirmation', [ReservationController::class, 'confirm'])
        ->name('reservations.confirm');
    Route::post('/reservations/{reservation}/release', [ReservationController::class, 'release'])
        ->name('reservations.release');
    Route::post('/reservations/{reservation}/allocation', [ReservationController::class, 'allocate'])
        ->name('reservations.allocate');
    Route::post('/reservations/{reservation}/pick', [FulfillmentController::class, 'pick'])
        ->name('reservations.pick');
    Route::post('/reservations/{reservation}/return-picked', [FulfillmentController::class, 'returnPicked'])
        ->name('reservations.return-picked');
    Route::post('/reservations/{reservation}/pack', [FulfillmentController::class, 'pack'])
        ->name('reservations.pack');
    Route::post('/reservations/{reservation}/unpack', [FulfillmentController::class, 'unpack'])
        ->name('reservations.unpack');

    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('/shipments/{shipment}/submission', [ShipmentController::class, 'submit'])
        ->name('shipments.submit');
    Route::post(
        '/shipments/{shipment}/provider-submissions/{providerSubmission}/reconciliation',
        [ShipmentController::class, 'reconcile'],
    )
        ->scopeBindings()
        ->name('shipments.provider-submissions.reconcile');

    Route::post('/shipments/{shipment}/mock-provider-scenario', [MockProviderControlController::class, 'setScenario'])
        ->name('shipments.mock-provider-scenario.store');
    Route::post(
        '/shipments/{shipment}/mock-provider-shipments/{mockProviderShipment}/handoff',
        [MockProviderControlController::class, 'sendHandoff'],
    )->name('shipments.mock-provider.handoff');
    Route::post(
        '/shipments/{shipment}/mock-provider-shipments/{mockProviderShipment}/delivery',
        [MockProviderControlController::class, 'sendDelivery'],
    )->name('shipments.mock-provider.delivery');
    Route::post(
        '/shipments/{shipment}/mock-provider-shipments/{mockProviderShipment}/out-of-order-delivery',
        [MockProviderControlController::class, 'sendOutOfOrderDelivery'],
    )->name('shipments.mock-provider.out-of-order-delivery');
    Route::post(
        '/mock-provider-shipments/{mockProviderShipment}/webhooks/{webhook}/replay',
        [MockProviderControlController::class, 'replay'],
    )
        ->scopeBindings()
        ->name('mock-provider.webhooks.replay');

    Route::get('/provider-webhook-receipts', [ProviderWebhookReceiptController::class, 'index'])
        ->name('provider-webhook-receipts.index');
    Route::get(
        '/provider-webhook-receipts/{providerWebhookReceipt}',
        [ProviderWebhookReceiptController::class, 'show'],
    )->name('provider-webhook-receipts.show');

    Route::get('/reports/inventory', [OperationalReportController::class, 'inventory'])
        ->name('reports.inventory');
    Route::get('/reports/reservations', [OperationalReportController::class, 'reservations'])
        ->name('reports.reservations');
    Route::get('/reports/consumed-orders', [OperationalReportController::class, 'consumedOrders'])
        ->name('reports.consumed-orders');
    Route::get('/reports/movements', [OperationalReportController::class, 'movements'])
        ->name('reports.movements');
});
