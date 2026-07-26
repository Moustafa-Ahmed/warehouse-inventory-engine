<?php

namespace App\Http\Controllers;

use App\Exceptions\WebhookIdentityConflictException;
use App\Http\Requests\ShippingProviderWebhookRequest;
use App\Jobs\ProcessProviderWebhookJob;
use App\Services\Shipping\WebhookReceiptService;
use Illuminate\Http\JsonResponse;

class ShippingProviderWebhookController extends Controller
{
    public function __invoke(
        ShippingProviderWebhookRequest $request,
        WebhookReceiptService $receipts,
    ): JsonResponse {
        try {
            $result = $receipts->receive($request->toInput());
        } catch (WebhookIdentityConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        if ($result->requiresProcessing) {
            ProcessProviderWebhookJob::dispatch($result->receiptId);
        }

        return response()->json([
            'receipt_id' => $result->receiptId,
            'duplicate' => ! $result->wasCreated,
            'processing' => $result->requiresProcessing ? 'pending' : 'complete',
        ], $result->requiresProcessing ? 202 : 200);
    }
}
