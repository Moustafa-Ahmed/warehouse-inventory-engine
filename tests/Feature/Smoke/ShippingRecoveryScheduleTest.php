<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

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
