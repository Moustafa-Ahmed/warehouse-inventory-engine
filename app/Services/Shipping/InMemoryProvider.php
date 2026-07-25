<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\Result;
use App\Enums\Shipping\Scenario;

final class InMemoryProvider implements ShippingProvider
{
    /** @var array<string, array{submission: Result, status: Result}> */
    private array $resultsByRequestKey = [];

    public function __construct(private readonly Scenario $scenario = Scenario::ImmediateSuccess) {}

    public function submit(Request $request): Result
    {
        if (array_key_exists($request->providerRequestKey, $this->resultsByRequestKey)) {
            return $this->resultsByRequestKey[$request->providerRequestKey]['submission'];
        }

        $externalShipmentId = $this->externalShipmentId($request->providerRequestKey);

        $submissionResult = new Result(
            providerRequestKey: $request->providerRequestKey,
            externalShipmentId: $this->scenario === Scenario::TimeoutThenSuccess ? null : $externalShipmentId,
            outcome: $this->scenario->responseOutcome(),
            callbackIntent: $this->scenario->callbackIntent(),
        );

        $this->resultsByRequestKey[$request->providerRequestKey] = [
            'submission' => $submissionResult,
            'status' => new Result(
                providerRequestKey: $request->providerRequestKey,
                externalShipmentId: $externalShipmentId,
                outcome: $this->scenario->providerOutcome(),
                callbackIntent: $this->scenario->callbackIntent(),
            ),
        ];

        return $submissionResult;
    }

    public function statusFor(string $providerRequestKey): ?Result
    {
        return $this->resultsByRequestKey[$providerRequestKey]['status'] ?? null;
    }

    private function externalShipmentId(string $providerRequestKey): ?string
    {
        if ($this->scenario === Scenario::PermanentFailure) {
            return null;
        }

        return 'mock-'.hash('sha256', $providerRequestKey);
    }
}
