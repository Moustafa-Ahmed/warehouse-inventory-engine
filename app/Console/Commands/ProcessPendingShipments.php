<?php

namespace App\Console\Commands;

use App\Enums\ProviderSubmissions\Status as SubmissionStatus;
use App\Enums\Shipments\Status;
use App\Jobs\SubmitShipmentJob;
use App\Models\Shipment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('shipments:process-pending {--limit=50 : Maximum shipments to dispatch}')]
#[Description('Dispatch eligible shipments pending provider submission')]
final class ProcessPendingShipments extends Command
{
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $shipmentIds = Shipment::query()
            ->where('status', Status::PendingHandoff->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('providerSubmissions')
                    ->orWhereHas(
                        'providerSubmissions',
                        fn (Builder $submissions): Builder => $submissions->where(
                            'status',
                            SubmissionStatus::Pending->value,
                        ),
                    );
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($shipmentIds as $shipmentId) {
            SubmitShipmentJob::dispatch((int) $shipmentId);
        }

        $this->info("Dispatched {$shipmentIds->count()} shipment(s).");

        return self::SUCCESS;
    }
}
