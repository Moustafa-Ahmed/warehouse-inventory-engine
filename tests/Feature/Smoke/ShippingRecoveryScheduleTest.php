<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

it('registers every bounded shipping recovery command with scheduler safety', function () {
    $requiredCommands = [
        'shipments:process-pending',
        'provider-submissions:reconcile-unknown',
        'mock-provider:dispatch-pending',
        'provider-webhooks:process-pending',
        'inventory:allocate-backorders',
        'reservations:expire',
    ];
    $registeredCommands = array_keys(Artisan::all());
    $scheduledEvents = collect(app(Schedule::class)->events());

    foreach ($requiredCommands as $requiredCommand) {
        expect($registeredCommands)->toContain($requiredCommand);

        /** @var Event|null $event */
        $event = $scheduledEvents->first(
            fn (Event $scheduledEvent): bool => str_contains(
                $scheduledEvent->command ?? '',
                $requiredCommand,
            ),
        );

        expect($event)->not->toBeNull()
            ->and($event?->expression)->toBe('* * * * *')
            ->and($event?->withoutOverlapping)->toBeTrue()
            ->and($event?->onOneServer)->toBeTrue();
    }
});

it('runs every recovery command safely when no work is eligible', function () {
    Queue::fake();
    config()->set('queue.default', 'database');

    $this->artisan('shipments:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 shipment(s).')
        ->assertSuccessful();
    $this->artisan('provider-submissions:reconcile-unknown', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 reconciliation job(s).')
        ->assertSuccessful();
    $this->artisan('mock-provider:dispatch-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 mock-provider callback(s).')
        ->assertSuccessful();
    $this->artisan('provider-webhooks:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 provider webhook receipt(s).')
        ->assertSuccessful();
    $this->artisan('inventory:allocate-backorders', [
        '--run-key' => 'empty-command-smoke',
        '--batch' => 10,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Allocated 0 backordered units.');
    $this->artisan('reservations:expire', ['--batch' => 10])
        ->assertSuccessful()
        ->expectsOutputToContain('Expired 0 temporary reservations.');

    Queue::assertNothingPushed();
});
