<?php

namespace App\Http\Controllers;

use App\Enums\Shipping\Scenario;
use App\Http\Requests\Shipping\MockProviderControlRequest;
use App\Http\Requests\Shipping\SetMockProviderScenarioRequest;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\Shipment;
use App\Services\Shipping\MockProviderControlService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use LogicException;

final class MockProviderControlController extends Controller
{
    public function setScenario(
        SetMockProviderScenarioRequest $request,
        Shipment $shipment,
        MockProviderControlService $controls,
    ): RedirectResponse {
        try {
            $controls->setNextScenario(
                $shipment->id,
                Scenario::from($request->validated('scenario')),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return $this->rejected($shipment, $exception);
        }

        return $this->completed($shipment, 'The next provider outcome was selected.');
    }

    public function sendHandoff(
        MockProviderControlRequest $request,
        Shipment $shipment,
        MockProviderShipment $mockProviderShipment,
        MockProviderControlService $controls,
    ): RedirectResponse {
        return $this->run(
            $shipment,
            $mockProviderShipment,
            fn (): int => $controls->sendHandoffConfirmation($mockProviderShipment->id),
            'Shipment-confirmation webhook queued for signed HTTP delivery.',
        );
    }

    public function sendDelivery(
        MockProviderControlRequest $request,
        Shipment $shipment,
        MockProviderShipment $mockProviderShipment,
        MockProviderControlService $controls,
    ): RedirectResponse {
        return $this->run(
            $shipment,
            $mockProviderShipment,
            fn (): int => $controls->sendDeliveryConfirmation($mockProviderShipment->id),
            'Delivery-confirmation webhook queued for signed HTTP delivery.',
        );
    }

    public function sendOutOfOrderDelivery(
        MockProviderControlRequest $request,
        Shipment $shipment,
        MockProviderShipment $mockProviderShipment,
        MockProviderControlService $controls,
    ): RedirectResponse {
        return $this->run(
            $shipment,
            $mockProviderShipment,
            fn (): int => $controls->sendOutOfOrderDelivery($mockProviderShipment->id),
            'Out-of-order delivery webhook queued for signed HTTP delivery.',
        );
    }

    public function replay(
        MockProviderControlRequest $request,
        MockProviderShipment $mockProviderShipment,
        MockProviderWebhook $webhook,
        MockProviderControlService $controls,
    ): RedirectResponse {
        $shipment = Shipment::query()->findOrFail((int) $mockProviderShipment->shipment_reference);

        try {
            $controls->replayWebhook($webhook->id);
        } catch (InvalidArgumentException|LogicException $exception) {
            return $this->rejected($shipment, $exception);
        }

        return $this->completed(
            $shipment,
            'The persisted webhook was queued again with the same event ID and raw body.',
        );
    }

    private function run(
        Shipment $shipment,
        MockProviderShipment $mockProviderShipment,
        \Closure $action,
        string $message,
    ): RedirectResponse {
        if ($mockProviderShipment->shipment_reference !== (string) $shipment->id) {
            abort(404);
        }

        try {
            $action();
        } catch (InvalidArgumentException|LogicException $exception) {
            return $this->rejected($shipment, $exception);
        }

        return $this->completed($shipment, $message);
    }

    private function completed(Shipment $shipment, string $message): RedirectResponse
    {
        return redirect()
            ->route('shipments.show', $shipment)
            ->with('status', $message);
    }

    private function rejected(
        Shipment $shipment,
        \Throwable $exception,
    ): RedirectResponse {
        return redirect()
            ->route('shipments.show', $shipment)
            ->withErrors(['mock_provider' => $exception->getMessage()])
            ->with('message_type', 'domain_rejection');
    }
}
