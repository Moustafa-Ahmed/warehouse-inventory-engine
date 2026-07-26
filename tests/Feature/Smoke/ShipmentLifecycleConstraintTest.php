<?php

use App\Enums\Shipments\Status;
use App\Models\Shipment;
use Illuminate\Database\QueryException;

it('accepts a shipped shipment with its shipment timestamp', function () {
    $shipment = Shipment::factory()->shipped()->create();

    expect($shipment->status)->toBe(Status::Shipped)
        ->and($shipment->shipped_at)->not->toBeNull();
});

it('rejects shipment states whose timestamp does not match their status', function (
    Status $status,
    bool $hasShippedAt,
) {
    expect(fn () => Shipment::factory()->create([
        'status' => $status,
        'shipped_at' => $hasShippedAt ? now() : null,
    ]))->toThrow(QueryException::class);
})->with([
    'pending handoff with shipment timestamp' => [Status::PendingHandoff, true],
    'shipped without shipment timestamp' => [Status::Shipped, false],
]);
