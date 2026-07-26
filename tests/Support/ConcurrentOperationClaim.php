<?php

namespace Tests\Support;

use App\Enums\Operations\Type;
use App\Models\Product;
use App\Services\Operations\OperationService;
use Closure;
use Illuminate\Support\Facades\DB;

final class ConcurrentOperationClaim
{
    /**
     * @param  array<string, mixed>  $request
     * @return Closure(): array<string, mixed>
     */
    public static function make(
        string $idempotencyKey,
        array $request,
        string $sku,
    ): Closure {
        return static function () use ($idempotencyKey, $request, $sku): array {
            return DB::transaction(
                fn (): array => app(OperationService::class)->execute(
                    Type::ReceiveStock,
                    $idempotencyKey,
                    $request,
                    function () use ($sku): array {
                        usleep(250_000);

                        $product = Product::query()->create([
                            'sku' => $sku,
                            'name' => 'Concurrent receipt marker',
                            'is_active' => true,
                        ]);

                        return ['product_id' => $product->id];
                    },
                ),
            );
        };
    }
}
