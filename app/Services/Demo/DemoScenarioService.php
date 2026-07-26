<?php

namespace App\Services\Demo;

use App\DTOs\Fulfillment\PackReservationInput;
use App\DTOs\Fulfillment\PickReservationInput;
use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\DTOs\Shipping\WebhookReceiptInput;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Scenario;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\MockProviderScenarioOverride;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Reservations\ReservationService;
use App\Services\Shipping\MockProviderControlService;
use App\Services\Shipping\ProviderWebhookService;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShipmentSubmissionService;
use App\Services\Shipping\WebhookReceiptService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

final class DemoScenarioService
{
    private const ORDER_PREFIX = 'DEMO-';

    private const OPERATION_PREFIX = 'demo:';

    private const PRODUCT_PREFIX = 'DEMO-';

    private const WAREHOUSE_CODE = 'DEMO-CAI';

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderService $orders,
        private readonly ReservationService $reservations,
        private readonly FulfillmentService $fulfillment,
        private readonly ShipmentService $shipments,
        private readonly ShipmentSubmissionService $submissions,
        private readonly MockProviderControlService $providerControls,
        private readonly WebhookReceiptService $webhookReceipts,
        private readonly ProviderWebhookService $providerWebhooks,
    ) {}

    /**
     * @return array<string, int>
     */
    public function setup(): array
    {
        $this->ensureAvailable();
        $warehouse = $this->demoWarehouse();
        $partialOrderId = $this->setupPartialAllocation($warehouse);
        $timeoutShipmentId = $this->setupPackedShipment(
            $warehouse,
            'TIMEOUT',
            Scenario::TimeoutThenSuccess,
        );
        $failedShipmentId = $this->setupPackedShipment(
            $warehouse,
            'FAILURE',
            Scenario::PermanentFailure,
        );
        $duplicateShipmentId = $this->setupPackedShipment(
            $warehouse,
            'DUPLICATE',
            Scenario::SuccessWithDuplicateDelivery,
        );
        $pendingReceiptShipmentId = $this->setupPackedShipment(
            $warehouse,
            'PENDING-WEBHOOK',
            Scenario::OutOfOrderDelivery,
        );
        $confirmationShipmentId = $this->setupPackedShipment(
            $warehouse,
            'CONFIRMATION',
            Scenario::ImmediateSuccess,
        );
        $pendingReceiptId = $this->persistPendingDeliveryReceipt(
            $pendingReceiptShipmentId,
        );

        return [
            'partial_order_id' => $partialOrderId,
            'timeout_shipment_id' => $timeoutShipmentId,
            'failed_shipment_id' => $failedShipmentId,
            'duplicate_shipment_id' => $duplicateShipmentId,
            'pending_receipt_shipment_id' => $pendingReceiptShipmentId,
            'pending_receipt_id' => $pendingReceiptId,
            'confirmation_shipment_id' => $confirmationShipmentId,
        ];
    }

    public function reset(): int
    {
        $this->ensureAvailable();

        return DB::transaction(function (): int {
            $orderIds = Order::query()
                ->where('order_number', 'like', self::ORDER_PREFIX.'%')
                ->pluck('id');
            $orderItemIds = OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->pluck('id');
            $reservationIds = Reservation::query()
                ->whereIn('order_item_id', $orderItemIds)
                ->pluck('id');
            $shipmentIds = Shipment::query()
                ->whereIn('order_id', $orderIds)
                ->pluck('id');
            $productIds = Product::query()
                ->where('sku', 'like', self::PRODUCT_PREFIX.'%')
                ->pluck('id');
            $mockShipmentIds = MockProviderShipment::query()
                ->whereIn('shipment_reference', $shipmentIds->map(
                    fn (mixed $id): string => (string) $id,
                ))
                ->pluck('id');
            $externalEventIds = MockProviderWebhook::query()
                ->whereIn('mock_provider_shipment_id', $mockShipmentIds)
                ->pluck('external_event_id');
            $receiptIds = ProviderWebhookReceipt::query()
                ->where('provider', 'mock')
                ->whereIn('external_event_id', $externalEventIds)
                ->pluck('id');
            $operationIds = InventoryMovement::query()
                ->whereIn('product_id', $productIds)
                ->pluck('operation_id')
                ->merge(
                    ReservationTransition::query()
                        ->whereIn('reservation_id', $reservationIds)
                        ->pluck('operation_id'),
                )
                ->unique();

            ProviderWebhookReceipt::query()->whereIn('id', $receiptIds)->delete();
            MockProviderWebhook::query()
                ->whereIn('mock_provider_shipment_id', $mockShipmentIds)
                ->delete();
            MockProviderScenarioOverride::query()
                ->whereIn('shipment_reference', $shipmentIds->map(
                    fn (mixed $id): string => (string) $id,
                ))
                ->delete();
            MockProviderShipment::query()->whereIn('id', $mockShipmentIds)->delete();
            ProviderSubmission::query()->whereIn('shipment_id', $shipmentIds)->delete();
            ShipmentItem::query()->whereIn('shipment_id', $shipmentIds)->delete();
            Shipment::query()->whereIn('id', $shipmentIds)->delete();
            ReservationTransition::query()
                ->whereIn('reservation_id', $reservationIds)
                ->delete();
            Reservation::query()->whereIn('id', $reservationIds)->delete();
            OrderItem::query()->whereIn('id', $orderItemIds)->delete();
            Order::query()->whereIn('id', $orderIds)->delete();
            InventoryMovement::query()->whereIn('product_id', $productIds)->delete();
            InventoryBalance::query()->whereIn('product_id', $productIds)->delete();
            Operation::query()
                ->whereIn('id', $operationIds)
                ->orWhere('idempotency_key', 'like', self::OPERATION_PREFIX.'%')
                ->orWhereIn(
                    'idempotency_key',
                    $receiptIds->map(
                        fn (mixed $id): string => 'provider-webhook-receipt-'.$id,
                    ),
                )
                ->delete();
            Product::query()->whereIn('id', $productIds)->delete();
            Warehouse::query()->where('code', self::WAREHOUSE_CODE)->delete();

            return $orderIds->count();
        }, attempts: 3);
    }

    /**
     * @return array{
     *     allocated_quantities: array<int, int>,
     *     available_quantity: int,
     *     reserved_quantity: int,
     *     order_ids: array<int, int>
     * }
     */
    public function runConcurrentReservation(): array
    {
        $this->ensureAvailable();
        $runId = Str::upper(Str::substr((string) Str::uuid(), 0, 8));
        $warehouse = Warehouse::query()->create([
            'code' => 'DEMO-CONCURRENT-'.$runId,
            'name' => 'Concurrent reservation demo '.$runId,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'sku' => 'DEMO-CONCURRENT-'.$runId,
            'name' => 'Final-unit concurrency demo '.$runId,
            'is_active' => true,
        ]);
        $users = [
            $this->demoUser('a'),
            $this->demoUser('b'),
        ];

        $this->withSynchronousQueue(fn () => $this->inventory->receive(
            new ReceiveStockInput(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantity: 1,
                sourceReference: 'concurrency-demo-'.$runId,
                idempotencyKey: self::OPERATION_PREFIX.'concurrent:receive:'.$runId,
            ),
        ));

        $orderItemIds = [];
        $orderIds = [];

        foreach ([0, 1] as $index) {
            $order = $this->orders->create(new CreateOrderInput(
                orderNumber: "DEMO-CONCURRENT-{$runId}-".($index + 1),
                items: [new CreateOrderItemInput($product->id, 1)],
                idempotencyKey: self::OPERATION_PREFIX."concurrent:order:{$runId}:{$index}",
            ));
            $orderIds[] = $order->orderId;
            $orderItemIds[] = $order->items[0]['order_item_id'];
        }

        $results = Concurrency::run([
            $this->reservationAttempt(
                $orderItemIds[0],
                $warehouse->id,
                self::OPERATION_PREFIX.'concurrent:reserve:'.$runId.':a',
                $users[0]->id,
            ),
            $this->reservationAttempt(
                $orderItemIds[1],
                $warehouse->id,
                self::OPERATION_PREFIX.'concurrent:reserve:'.$runId.':b',
                $users[1]->id,
            ),
        ]);
        $allocatedQuantities = array_column($results, 'allocated_quantity');
        sort($allocatedQuantities);
        $balance = InventoryBalance::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->sole();

        if (
            $allocatedQuantities !== [0, 1]
            || $balance->available_quantity !== 0
            || $balance->reserved_quantity !== 1
        ) {
            throw new LogicException(
                'The concurrent reservation demonstration did not preserve the final unit.'
            );
        }

        return [
            'allocated_quantities' => $allocatedQuantities,
            'available_quantity' => $balance->available_quantity,
            'reserved_quantity' => $balance->reserved_quantity,
            'order_ids' => $orderIds,
        ];
    }

    private function setupPartialAllocation(Warehouse $warehouse): int
    {
        $product = $this->demoProduct(
            'PARTIAL',
            'Partial allocation demo product',
        );
        $this->receiveDemoStock($product, $warehouse, 6, 'partial');
        $order = $this->orders->create(new CreateOrderInput(
            orderNumber: 'DEMO-PARTIAL-001',
            items: [new CreateOrderItemInput($product->id, 10)],
            idempotencyKey: self::OPERATION_PREFIX.'partial:order',
        ));
        $this->reservations->reserve(new ReserveOrderItemInput(
            orderItemId: $order->items[0]['order_item_id'],
            warehouseId: $warehouse->id,
            idempotencyKey: self::OPERATION_PREFIX.'partial:reserve',
            source: 'demo_setup',
        ));

        return $order->orderId;
    }

    private function setupPackedShipment(
        Warehouse $warehouse,
        string $key,
        Scenario $scenario,
    ): int {
        $slug = Str::lower($key);
        $product = $this->demoProduct(
            $key,
            Str::headline($slug).' demo product',
        );
        $this->receiveDemoStock($product, $warehouse, 2, $slug);
        $order = $this->orders->create(new CreateOrderInput(
            orderNumber: 'DEMO-'.$key.'-001',
            items: [new CreateOrderItemInput($product->id, 2)],
            idempotencyKey: self::OPERATION_PREFIX.$slug.':order',
        ));
        $reservation = $this->reservations->reserve(new ReserveOrderItemInput(
            orderItemId: $order->items[0]['order_item_id'],
            warehouseId: $warehouse->id,
            idempotencyKey: self::OPERATION_PREFIX.$slug.':reserve',
            source: 'demo_setup',
        ));
        $this->fulfillment->pick(new PickReservationInput(
            reservationId: $reservation->reservationId,
            quantity: 2,
            idempotencyKey: self::OPERATION_PREFIX.$slug.':pick',
            source: 'demo_setup',
        ));
        $this->fulfillment->pack(new PackReservationInput(
            reservationId: $reservation->reservationId,
            quantity: 2,
            idempotencyKey: self::OPERATION_PREFIX.$slug.':pack',
            source: 'demo_setup',
        ));
        $shipment = $this->shipments->create(new CreateShipmentInput(
            orderId: $order->orderId,
            warehouseId: $warehouse->id,
            items: [new CreateShipmentItemInput($reservation->reservationId, 2)],
            idempotencyKey: self::OPERATION_PREFIX.$slug.':shipment',
        ));

        if (! ProviderSubmission::query()->where('shipment_id', $shipment->shipmentId)->exists()) {
            $this->providerControls->setNextScenario($shipment->shipmentId, $scenario);
            $this->submissions->submit($shipment->shipmentId);
        }

        return $shipment->shipmentId;
    }

    private function persistPendingDeliveryReceipt(int $shipmentId): int
    {
        $mockShipment = MockProviderShipment::query()
            ->where('shipment_reference', (string) $shipmentId)
            ->sole();
        $webhook = $mockShipment->webhooks()
            ->where('event_type', EventType::DeliveryConfirmed->value)
            ->sole();
        /** @var array<string, mixed> $payload */
        $payload = json_decode($webhook->raw_body, true, flags: JSON_THROW_ON_ERROR);
        $result = $this->webhookReceipts->receive(new WebhookReceiptInput(
            provider: 'mock',
            externalEventId: $webhook->external_event_id,
            eventType: EventType::DeliveryConfirmed,
            rawBody: $webhook->raw_body,
            occurredAt: CarbonImmutable::parse((string) $payload['occurred_at']),
        ));
        $this->providerWebhooks->process($result->receiptId);

        return $result->receiptId;
    }

    private function demoWarehouse(): Warehouse
    {
        return Warehouse::query()->updateOrCreate(
            ['code' => self::WAREHOUSE_CODE],
            [
                'name' => 'Cairo demonstration warehouse',
                'is_active' => true,
            ],
        );
    }

    private function demoProduct(string $skuSuffix, string $name): Product
    {
        return Product::query()->updateOrCreate(
            ['sku' => self::PRODUCT_PREFIX.$skuSuffix],
            [
                'name' => $name,
                'is_active' => true,
            ],
        );
    }

    private function receiveDemoStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $key,
    ): void {
        $idempotencyKey = self::OPERATION_PREFIX.$key.':receive';

        if (Operation::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $this->withSynchronousQueue(fn () => $this->inventory->receive(
            new ReceiveStockInput(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantity: $quantity,
                sourceReference: 'demo-'.$key,
                idempotencyKey: $idempotencyKey,
            ),
        ));
    }

    private function demoUser(string $suffix): User
    {
        return User::query()->firstOrCreate(
            ['email' => "demo.reserver.{$suffix}@example.test"],
            [
                'name' => 'Demo reserver '.Str::upper($suffix),
                'password' => Hash::make(Str::random(40)),
            ],
        );
    }

    /**
     * @return Closure(): array{allocated_quantity: int}
     */
    private function reservationAttempt(
        int $orderItemId,
        int $warehouseId,
        string $idempotencyKey,
        int $actorId,
    ): Closure {
        return static function () use (
            $orderItemId,
            $warehouseId,
            $idempotencyKey,
            $actorId,
        ): array {
            usleep(100_000);
            $result = app(ReservationService::class)->reserve(
                new ReserveOrderItemInput(
                    orderItemId: $orderItemId,
                    warehouseId: $warehouseId,
                    idempotencyKey: $idempotencyKey,
                    actorId: $actorId,
                    source: 'demo_concurrency',
                ),
            );

            return ['allocated_quantity' => $result->allocatedQuantity];
        };
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function withSynchronousQueue(Closure $callback): mixed
    {
        $previousConnection = config('queue.default');
        config()->set('queue.default', 'sync');

        try {
            return $callback();
        } finally {
            config()->set('queue.default', $previousConnection);
        }
    }

    private function ensureAvailable(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new LogicException(
                'Demonstration scenarios are available only in local and testing environments.'
            );
        }
    }
}
