<?php

namespace App\Http\Controllers;

use App\Enums\Reservations\Kind as ReservationKind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Enums\Shipping\Scenario;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\Shipping\CreateShipmentRequest;
use App\Http\Requests\Shipping\ReconcileProviderSubmissionRequest;
use App\Http\Requests\Shipping\ShipmentSubmissionRequest;
use App\Jobs\SubmitShipmentJob;
use App\Models\MockProviderScenarioOverride;
use App\Models\MockProviderShipment;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class ShipmentController extends Controller
{
    public function index(): View
    {
        return view('shipments.index', [
            'shipments' => Shipment::query()
                ->with([
                    'order:id,order_number',
                    'warehouse:id,code,name',
                ])
                ->withCount('items')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $packedReservations = Reservation::query()
            ->with([
                'orderItem.order:id,order_number',
                'orderItem.product:id,sku,name',
                'warehouse:id,code,name',
                'shipmentItems.shipment:id,status',
            ])
            ->where('kind', ReservationKind::Confirmed->value)
            ->where('status', ReservationStatus::Open->value)
            ->where('packed_quantity', '>', 0)
            ->orderBy('id')
            ->get();
        $groups = $packedReservations
            ->map(fn (Reservation $reservation): array => [
                'key' => $reservation->orderItem->order_id.':'.$reservation->warehouse_id,
                'order_id' => $reservation->orderItem->order_id,
                'order_number' => $reservation->orderItem->order->order_number,
                'warehouse_id' => $reservation->warehouse_id,
                'warehouse_code' => $reservation->warehouse->code,
            ])
            ->unique('key')
            ->values();
        $selectedGroup = $this->selectedGroup($request->string('group')->toString(), $groups);
        $reservationRows = collect();

        if ($selectedGroup !== null) {
            $reservationRows = $packedReservations
                ->filter(fn (Reservation $reservation): bool => $reservation->orderItem->order_id === $selectedGroup['order_id']
                    && $reservation->warehouse_id === $selectedGroup['warehouse_id'])
                ->map(function (Reservation $reservation): array {
                    $pendingAssignedQuantity = $reservation->shipmentItems
                        ->filter(fn ($item): bool => $item->shipment->status === ShipmentStatus::PendingHandoff)
                        ->sum('quantity');

                    return [
                        'reservation' => $reservation,
                        'unassigned_quantity' => max(
                            0,
                            $reservation->packed_quantity - $pendingAssignedQuantity,
                        ),
                    ];
                })
                ->filter(fn (array $row): bool => $row['unassigned_quantity'] > 0)
                ->values();
        }

        return view('shipments.create', [
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'reservationRows' => $reservationRows,
            'operationKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        CreateShipmentRequest $request,
        ShipmentService $shipments,
    ): RedirectResponse {
        try {
            $result = $shipments->create($request->toInput());
        } catch (IdempotencyConflictException $exception) {
            return redirect()
                ->route('shipments.create')
                ->withInput()
                ->withErrors(['shipment_operation_key' => $exception->getMessage()])
                ->with('message_type', 'conflict');
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('shipments.create')
                ->withInput()
                ->withErrors(['shipment' => $exception->getMessage()])
                ->with('message_type', 'domain_rejection');
        }

        return redirect()
            ->route('shipments.show', $result->shipmentId)
            ->with('status', 'Shipment composed from packed inventory.')
            ->with('operation_result', [
                'operation_id' => $result->operationId,
                'shipment_id' => $result->shipmentId,
            ]);
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load([
            'order:id,order_number',
            'warehouse:id,code,name',
            'items.reservation.orderItem.product:id,sku,name',
            'providerSubmissions' => fn ($query) => $query->latest('id'),
        ]);
        $mockProviderShipments = MockProviderShipment::query()
            ->with(['webhooks' => fn ($query) => $query->latest('id')])
            ->where('shipment_reference', (string) $shipment->id)
            ->latest('id')
            ->get();
        $externalEventIds = $mockProviderShipments
            ->flatMap(fn (MockProviderShipment $mockShipment) => $mockShipment->webhooks->pluck('external_event_id'))
            ->all();

        return view('shipments.show', [
            'shipment' => $shipment,
            'mockProviderShipments' => $mockProviderShipments,
            'providerWebhookReceipts' => ProviderWebhookReceipt::query()
                ->when(
                    $externalEventIds === [],
                    fn ($query) => $query->whereRaw('1 = 0'),
                    fn ($query) => $query->whereIn('external_event_id', $externalEventIds),
                )
                ->latest('id')
                ->get(),
            'scenarioOverride' => MockProviderScenarioOverride::query()
                ->where('shipment_reference', (string) $shipment->id)
                ->first(),
            'scenarios' => Scenario::cases(),
            'mockControlsAvailable' => app()->environment(['local', 'testing']),
        ]);
    }

    public function submit(
        ShipmentSubmissionRequest $request,
        Shipment $shipment,
        ShipmentSubmissionService $submissions,
    ): RedirectResponse {
        try {
            $prepared = $submissions->prepare($shipment->id);
            SubmitShipmentJob::dispatch($shipment->id);
        } catch (InvalidArgumentException $exception) {
            return $this->rejected($shipment, 'submission', $exception);
        }

        return redirect()
            ->route('shipments.show', $shipment)
            ->with('status', 'Shipment submission queued with its stable provider request identity.')
            ->with('provider_submission_id', $prepared->providerSubmissionId);
    }

    public function reconcile(
        ReconcileProviderSubmissionRequest $request,
        Shipment $shipment,
        ProviderSubmission $providerSubmission,
        ShipmentSubmissionService $submissions,
    ): RedirectResponse {
        try {
            $result = $submissions->reconcile($providerSubmission->id);
        } catch (Throwable) {
            return redirect()
                ->route('shipments.show', $shipment)
                ->withErrors([
                    'reconciliation' => 'Provider reconciliation failed. Retry later or inspect the application log.',
                ])
                ->with('message_type', 'domain_rejection');
        }

        return redirect()
            ->route('shipments.show', $shipment)
            ->with(
                'status',
                $result === null
                    ? 'No provider status change was available.'
                    : 'Provider status reconciled. Shipment confirmation still requires the signed webhook.',
            );
    }

    /**
     * @param  Collection<int, array{key: string, order_id: int, order_number: string, warehouse_id: int, warehouse_code: string}>  $groups
     * @return array{key: string, order_id: int, order_number: string, warehouse_id: int, warehouse_code: string}|null
     */
    private function selectedGroup(string $key, Collection $groups): ?array
    {
        if ($key === '') {
            return null;
        }

        return $groups->firstWhere('key', $key);
    }

    private function rejected(
        Shipment $shipment,
        string $field,
        Throwable $exception,
    ): RedirectResponse {
        return redirect()
            ->route('shipments.show', $shipment)
            ->withErrors([$field => $exception->getMessage()])
            ->with('message_type', 'domain_rejection');
    }
}
