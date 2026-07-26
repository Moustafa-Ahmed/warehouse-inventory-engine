<?php

use App\Services\Orders\OrderItemProgressCalculator;

test('it calculates each order item quantity dimension', function (
    array $quantities,
    array $expected,
) {
    $progress = app(OrderItemProgressCalculator::class)->calculate(...$quantities);

    expect($progress)
        ->outstandingQuantity->toBe($expected['outstanding'])
        ->allocatedQuantity->toBe($expected['allocated'])
        ->unshippedUncancelledQuantity->toBe($expected['unshipped_uncancelled'])
        ->undeliveredShippedQuantity->toBe($expected['undelivered_shipped']);
})->with([
    'empty item' => [
        [
            'orderedQuantity' => 0,
            'cancelledQuantity' => 0,
            'reservedQuantity' => 0,
            'pickedQuantity' => 0,
            'packedQuantity' => 0,
            'shippedQuantity' => 0,
            'deliveredQuantity' => 0,
        ],
        [
            'outstanding' => 0,
            'allocated' => 0,
            'unshipped_uncancelled' => 0,
            'undelivered_shipped' => 0,
        ],
    ],
    'new demand' => [
        [
            'orderedQuantity' => 10,
            'cancelledQuantity' => 0,
            'reservedQuantity' => 0,
            'pickedQuantity' => 0,
            'packedQuantity' => 0,
            'shippedQuantity' => 0,
            'deliveredQuantity' => 0,
        ],
        [
            'outstanding' => 10,
            'allocated' => 0,
            'unshipped_uncancelled' => 10,
            'undelivered_shipped' => 0,
        ],
    ],
    'partially allocated' => [
        [
            'orderedQuantity' => 10,
            'cancelledQuantity' => 0,
            'reservedQuantity' => 6,
            'pickedQuantity' => 0,
            'packedQuantity' => 0,
            'shippedQuantity' => 0,
            'deliveredQuantity' => 0,
        ],
        [
            'outstanding' => 4,
            'allocated' => 6,
            'unshipped_uncancelled' => 10,
            'undelivered_shipped' => 0,
        ],
    ],
    'progressing through separate stages' => [
        [
            'orderedQuantity' => 10,
            'cancelledQuantity' => 1,
            'reservedQuantity' => 1,
            'pickedQuantity' => 2,
            'packedQuantity' => 1,
            'shippedQuantity' => 5,
            'deliveredQuantity' => 3,
        ],
        [
            'outstanding' => 0,
            'allocated' => 9,
            'unshipped_uncancelled' => 4,
            'undelivered_shipped' => 2,
        ],
    ],
    'fully shipped or cancelled and delivered' => [
        [
            'orderedQuantity' => 10,
            'cancelledQuantity' => 2,
            'reservedQuantity' => 0,
            'pickedQuantity' => 0,
            'packedQuantity' => 0,
            'shippedQuantity' => 8,
            'deliveredQuantity' => 8,
        ],
        [
            'outstanding' => 0,
            'allocated' => 8,
            'unshipped_uncancelled' => 0,
            'undelivered_shipped' => 0,
        ],
    ],
]);

test('it rejects quantities that violate conservation', function (array $quantities) {
    expect(fn () => app(OrderItemProgressCalculator::class)->calculate(...$quantities))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'negative quantity' => [[
        'orderedQuantity' => 10,
        'cancelledQuantity' => -1,
        'reservedQuantity' => 0,
        'pickedQuantity' => 0,
        'packedQuantity' => 0,
        'shippedQuantity' => 0,
        'deliveredQuantity' => 0,
    ]],
    'stage total exceeds order' => [[
        'orderedQuantity' => 10,
        'cancelledQuantity' => 1,
        'reservedQuantity' => 5,
        'pickedQuantity' => 5,
        'packedQuantity' => 0,
        'shippedQuantity' => 0,
        'deliveredQuantity' => 0,
    ]],
    'delivered exceeds shipped' => [[
        'orderedQuantity' => 10,
        'cancelledQuantity' => 0,
        'reservedQuantity' => 0,
        'pickedQuantity' => 0,
        'packedQuantity' => 0,
        'shippedQuantity' => 4,
        'deliveredQuantity' => 5,
    ]],
]);
