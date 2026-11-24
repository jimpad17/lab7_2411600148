<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Shared search / filter logic (also used by the CSV export).
     */
    private function filtered(Request $request)
    {
        return Product::applyFilters($request->all());
    }

    private function rules(?Product $product = null): array
    {
        $uniqueSku = Rule::unique('products', 'sku');
        if ($product) {
            $uniqueSku->ignore($product->id);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => [
                'required', 'string', 'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $uniqueSku,
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'supplier' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.required' => 'Product ID / SKU is required.',
            'sku.regex' => 'Product ID / SKU may contain letters, numbers, dots, hyphens and underscores.',
            'sku.unique' => 'This SKU is already used by another product.',
            'brand_id.required' => 'Please choose a brand.',
            'category_id.required' => 'Please choose a category.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reorder_level.min' => 'Reorder level cannot be negative.',
            'unit_price.min' => 'Unit price must be at least 0.01.',
        ];
    }

    /**
     * Basic data sanitization before saving to MySQL.
     */
    private function sanitized(array $data): array
    {
        $data['name'] = strip_tags(trim($data['name']));
        $data['sku'] = strtoupper(strip_tags(trim($data['sku'])));
        $data['description'] = isset($data['description']) ? strip_tags(trim($data['description'])) : null;
        $data['supplier'] = isset($data['supplier']) ? strip_tags(trim($data['supplier'])) : null;

        return $data;
    }

    public function index(Request $request)
    {
        return view('products.index', [
            'products' => $this->filtered($request)->with(['brand', 'category'])->paginate(10)->withQueryString(),
            'brands' => \App\Models\Brand::orderBy('name')->get(),
            'categories' => \App\Models\Category::orderBy('name')->get(),
            'suppliers' => \App\Models\Supplier::active()->orderBy('company_name')->get(),
            'filters' => $request->only(['brand_id', 'category_id', 'supplier_id', 'status', 'min_quantity', 'max_quantity', 'search']),
        ]);
    }

    public function create()
    {
        return view('products.create', [
            'product' => new Product(),
            'brands' => \App\Models\Brand::active()->orderBy('name')->get(),
            'categories' => \App\Models\Category::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->sanitized($request->validate($this->rules(), $this->messages()));
        $data['is_active'] = $request->boolean('is_active', true);

        // Lab 6: start from zero and let the observer apply the initial-stock
        // transaction, so the quantity is written in exactly one place.
        $initialQuantity = (int) $data['quantity'];
        $data['quantity'] = 0;

        $product = Product::create($data);

        if ($initialQuantity > 0) {
            InventoryTransaction::create([
                'product_id' => $product->id,
                'type' => InventoryTransaction::TYPE_IN,
                'direction' => InventoryTransaction::DIRECTION_IN,
                'quantity' => $initialQuantity,
                'reference_number' => 'INITIAL-STOCK',
                'notes' => 'Opening balance entered when the product was created.',
                'user_id' => auth()->id(),
            ]);
            $product->refresh();
        }

        ActivityLogger::log('created', 'Products', "Created product '{$product->name}' ({$product->sku}).");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' was added to the inventory.");
    }

    public function show(Request $request, Product $product)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $transactions = $product->transactions()
            ->with('user')
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('products.show', [
            'product' => $product->load(['brand', 'category']),
            'transactions' => $transactions,
            'filters' => $filters,
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', [
            'product' => $product,
            'brands' => \App\Models\Brand::orderBy('name')->get(),
            'categories' => \App\Models\Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->sanitized($request->validate($this->rules($product), $this->messages()));
        $data['is_active'] = $request->boolean('is_active');
        $oldQuantity = (int) $product->quantity;
        $newQuantity = (int) $data['quantity'];

        // Keep the previous balance on the product row; the observer writes the
        // new balance when the adjustment transaction is created.
        $product->update(array_merge($data, ['quantity' => $oldQuantity]));

        if ($newQuantity !== $oldQuantity) {
            InventoryTransaction::create([
                'product_id' => $product->id,
                'type' => InventoryTransaction::TYPE_ADJUSTMENT,
                'direction' => $newQuantity > $oldQuantity
                    ? InventoryTransaction::DIRECTION_IN
                    : InventoryTransaction::DIRECTION_OUT,
                'quantity' => abs($newQuantity - $oldQuantity),
                'reference_number' => 'PRODUCT-EDIT',
                'notes' => 'Quantity changed from the product edit form.',
                'user_id' => auth()->id(),
            ]);
            $product->refresh();
        }

        ActivityLogger::log('updated', 'Products', "Updated product '{$product->name}'.");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' was updated successfully.");
    }

    /**
     * Products are NEVER deleted. Deactivating keeps the record and its history
     * intact while removing it from active inventory lists.
     */
    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        ActivityLogger::log('deactivated', 'Products', "Marked product '{$product->name}' as unavailable (inactive).");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' is now unavailable (inactive). No records were deleted.");
    }

    public function restore(Product $product)
    {
        $product->update(['is_active' => true]);

        ActivityLogger::log('restored', 'Products', "Restored product '{$product->name}' to active inventory.");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' was restored to active inventory.");
    }

    /** Lab 7: CSV/PDF exports are delegated to the ProductsExport class. */
    public function exportCsv(Request $request, ProductsExport $exports)
    {
        return $exports->withFilters($request->only([
            'brand_id', 'category_id', 'supplier_id', 'status', 'min_quantity', 'max_quantity', 'search',
        ]))->csv();
    }

    public function exportPdf(Request $request, ProductsExport $exports)
    {
        return $exports->withFilters($request->only([
            'brand_id', 'category_id', 'supplier_id', 'status', 'min_quantity', 'max_quantity', 'search',
        ]))->pdf();
    }
}
