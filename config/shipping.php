<?php

use App\Enums\Shipping\Scenario;

return [
    'mock_provider' => [
        'callback_delay_seconds' => (int) env('MOCK_PROVIDER_CALLBACK_DELAY_SECONDS', 60),

        'scenario_weights' => [
            Scenario::ImmediateSuccess->value => (int) env('MOCK_PROVIDER_IMMEDIATE_SUCCESS_WEIGHT', 40),
            Scenario::DelayedSuccess->value => (int) env('MOCK_PROVIDER_DELAYED_SUCCESS_WEIGHT', 20),
            Scenario::PermanentFailure->value => (int) env('MOCK_PROVIDER_PERMANENT_FAILURE_WEIGHT', 10),
            Scenario::TimeoutThenSuccess->value => (int) env('MOCK_PROVIDER_TIMEOUT_THEN_SUCCESS_WEIGHT', 10),
            Scenario::SuccessWithDuplicateDelivery->value => (int) env('MOCK_PROVIDER_DUPLICATE_DELIVERY_WEIGHT', 10),
            Scenario::OutOfOrderDelivery->value => (int) env('MOCK_PROVIDER_OUT_OF_ORDER_DELIVERY_WEIGHT', 10),
        ],
    ],
];
