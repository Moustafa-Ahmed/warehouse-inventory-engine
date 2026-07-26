<?php

namespace App\Console\Commands;

use App\Enums\ProviderSubmissions\Status;
use App\Jobs\ReconcileProviderSubmissionJob;
use App\Models\ProviderSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('provider-submissions:reconcile-unknown {--limit=50 : Maximum submissions to dispatch}')]
#[Description('Dispatch reconciliation for provider submissions with unknown outcomes')]
final class ReconcileUnknownProviderSubmissions extends Command
{
    public function handle(): int
    {
        $submissionIds = ProviderSubmission::query()
            ->where('status', Status::Unknown->value)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id');

        foreach ($submissionIds as $submissionId) {
            ReconcileProviderSubmissionJob::dispatch((int) $submissionId);
        }

        $this->info("Dispatched {$submissionIds->count()} reconciliation job(s).");

        return self::SUCCESS;
    }
}
