<?php

namespace App\Console\Commands;

use App\Services\Reservations\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire {--batch=50 : Maximum temporary holds to expire}')]
#[Description('Release and expire eligible temporary reservations')]
final class ExpireReservationsCommand extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $batchSize = (int) $this->option('batch');

        if ($batchSize < 1 || $batchSize > 500) {
            $this->components->error('Batch must be between 1 and 500.');

            return self::FAILURE;
        }

        $expiredCount = $reservations->expireDueReservations($batchSize);

        $this->components->info("Expired {$expiredCount} temporary reservations.");

        return self::SUCCESS;
    }
}
