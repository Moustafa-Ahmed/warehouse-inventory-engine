<?php

namespace App\Services\Shipping;

use App\Enums\ProviderSubmissions\Status as SubmissionStatus;
use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Shipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ShippingReportService
{
    public function pendingHandoff(int $perPage = 10): LengthAwarePaginator
    {
        return Shipment::query()
            ->with([
                'order:id,order_number',
                'warehouse:id,code,name',
            ])
            ->withCount('items')
            ->where('status', ShipmentStatus::PendingHandoff->value)
            ->oldest('created_at')
            ->oldest('id')
            ->paginate($perPage);
    }

    public function providerSubmissionsNeedingAttention(
        int $perPage = 10,
    ): LengthAwarePaginator {
        return ProviderSubmission::query()
            ->with([
                'shipment.order:id,order_number',
                'shipment.warehouse:id,code,name',
            ])
            ->whereIn('status', [
                SubmissionStatus::Unknown->value,
                SubmissionStatus::PermanentlyFailed->value,
            ])
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function pendingWebhookReceipts(
        int $perPage = 10,
    ): LengthAwarePaginator {
        return ProviderWebhookReceipt::query()
            ->where('status', ReceiptStatus::Pending->value)
            ->oldest('occurred_at')
            ->oldest('id')
            ->paginate($perPage);
    }
}
