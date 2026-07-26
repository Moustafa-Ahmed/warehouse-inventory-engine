<?php

namespace App\Models;

use App\Enums\Shipping\Scenario;
use Database\Factories\MockProviderScenarioOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shipment_reference', 'scenario'])]
class MockProviderScenarioOverride extends Model
{
    /** @use HasFactory<MockProviderScenarioOverrideFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scenario' => Scenario::class,
        ];
    }
}
