<?php

namespace App\Http\Controllers;

use App\Models\ProviderWebhookReceipt;
use Illuminate\Contracts\View\View;

final class ProviderWebhookReceiptController extends Controller
{
    public function index(): View
    {
        return view('provider-webhook-receipts.index', [
            'receipts' => ProviderWebhookReceipt::query()
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function show(ProviderWebhookReceipt $providerWebhookReceipt): View
    {
        return view('provider-webhook-receipts.show', [
            'receipt' => $providerWebhookReceipt,
        ]);
    }
}
