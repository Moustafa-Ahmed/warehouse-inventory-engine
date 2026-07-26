<?php

use App\Enums\Shipping\Scenario;

return [
    'webhook' => [
        'replay_window_seconds' => (int) env('SHIPPING_WEBHOOK_REPLAY_WINDOW_SECONDS', 300),
        'rate_limit_per_minute' => (int) env('SHIPPING_WEBHOOK_RATE_LIMIT_PER_MINUTE', 120),

        'providers' => [
            'mock' => [
                'secret' => env('MOCK_PROVIDER_WEBHOOK_SECRET'),
            ],
        ],
    ],

    'mock_provider' => [
        'callback_delay_seconds' => (int) env('MOCK_PROVIDER_CALLBACK_DELAY_SECONDS', 60),
        'webhook_url' => env('MOCK_PROVIDER_WEBHOOK_URL'),
        'connect_timeout_seconds' => (int) env('MOCK_PROVIDER_CONNECT_TIMEOUT_SECONDS', 2),
        'request_timeout_seconds' => (int) env('MOCK_PROVIDER_REQUEST_TIMEOUT_SECONDS', 10),
        'maximum_delivery_attempts' => (int) env('MOCK_PROVIDER_MAXIMUM_DELIVERY_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('MOCK_PROVIDER_RETRY_BASE_SECONDS', 15),
        'delivery_lease_seconds' => (int) env('MOCK_PROVIDER_DELIVERY_LEASE_SECONDS', 60),
        'dispatch_batch_size' => (int) env('MOCK_PROVIDER_DISPATCH_BATCH_SIZE', 50),

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
