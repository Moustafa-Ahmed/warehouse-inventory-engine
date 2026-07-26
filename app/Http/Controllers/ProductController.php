<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveProductRequest;
use App\Models\Product;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ProductController extends Controller
{
    public function index(): View
    {
        return view('catalog.products.index', [
            'products' => Product::query()
                ->orderByDesc('is_active')
                ->orderBy('sku')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('catalog.products.form', ['product' => null]);
    }

    public function store(
        SaveProductRequest $request,
        ProductCatalogService $products,
    ): RedirectResponse {
        $product = $products->create($request->toInput());

        return redirect()
            ->route('products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('catalog.products.form', ['product' => $product]);
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        ProductCatalogService $products,
    ): RedirectResponse {
        $products->update($product, $request->toInput());

        return redirect()
            ->route('products.edit', $product)
            ->with('status', 'Product updated.');
    }
}
