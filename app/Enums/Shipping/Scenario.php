<?php

namespace App\Enums\Shipping;

enum Scenario: string
{
    case ImmediateSuccess = 'immediate_success';
    case DelayedSuccess = 'delayed_success';
    case PermanentFailure = 'permanent_failure';
    case TimeoutThenSuccess = 'timeout_then_success';
    case SuccessWithDuplicateDelivery = 'success_with_duplicate_delivery';
    case OutOfOrderDelivery = 'out_of_order_delivery';

    public function callbackIntent(): ?CallbackIntent
    {
        return match ($this) {
            self::ImmediateSuccess => CallbackIntent::Immediate,
            self::DelayedSuccess, self::TimeoutThenSuccess => CallbackIntent::Delayed,
            self::SuccessWithDuplicateDelivery => CallbackIntent::Duplicate,
            self::OutOfOrderDelivery => CallbackIntent::OutOfOrder,
            self::PermanentFailure => null,
        };
    }

    public function providerOutcome(): Outcome
    {
        return match ($this) {
            self::PermanentFailure => Outcome::PermanentlyFailed,
            default => Outcome::Accepted,
        };
    }

    public function responseOutcome(): Outcome
    {
        return match ($this) {
            self::TimeoutThenSuccess => Outcome::Uncertain,
            default => $this->providerOutcome(),
        };
    }
}
