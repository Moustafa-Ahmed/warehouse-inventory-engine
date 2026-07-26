<?php

namespace App\Http\Controllers;

use App\Services\Inventory\InventoryReportService;
use App\Services\Reservations\ReservationReportService;
use App\Services\Shipping\ShippingReportService;
use Illuminate\Contracts\View\View;

final class OperationalDashboardController extends Controller
{
    public function __invoke(
        ReservationReportService $reservations,
        ShippingReportService $shipping,
        InventoryReportService $inventory,
    ): View {
        return view('operations.home', [
            'partialAllocations' => $reservations->partialAllocations(perPage: 5),
            'expiringReservations' => $reservations->expiringReservations(
                cutoff: now()->toImmutable()->addDay(),
                perPage: 5,
            ),
            'pendingShipments' => $shipping->pendingHandoff(perPage: 5),
            'providerSubmissions' => $shipping
                ->providerSubmissionsNeedingAttention(perPage: 5),
            'pendingWebhookReceipts' => $shipping
                ->pendingWebhookReceipts(perPage: 5),
            'recentMovements' => $inventory->movements(perPage: 6),
        ]);
    }
}
