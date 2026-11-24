<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\SupplierPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /** Supplier master data with search, active filter and sorting. */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'trashed'])],
            'sort' => ['nullable', Rule::in(['company_name', 'lead_time_days', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Supplier::query()
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? 'all') === 'active', fn ($q) => $q->active())
            ->when(($filters['status'] ?? 'all') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? 'all') === 'trashed', fn ($q) => $q->onlyTrashed())
            ->orderBy($filters['sort'] ?? 'company_name', $filters['direction'] ?? 'asc');

        $suppliers = $query->withCount(['products', 'purchaseOrders'])->paginate(10)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'filters'));
    }

    public function create()
    {
        return view('suppliers.create', [
            'supplier' => new Supplier(['is_active' => true, 'payment_terms' => 'Net 30', 'lead_time_days' => 7]),
            'categories' => Category::active()->orderBy('name')->get(),
            'paymentTerms' => Supplier::paymentTermsOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSupplier($request);
        $data['is_active'] = $request->boolean('is_active');
        $categories = $data['categories'] ?? [];
        unset($data['categories']);

        $supplier = DB::transaction(function () use ($data, $categories) {
            $supplier = Supplier::create($data);
            $supplier->categories()->sync($categories);

            return $supplier;
        });

        ActivityLogger::log('created', 'Suppliers', "Created supplier '{$supplier->company_name}'.");

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier added successfully.');
    }

    public function show(Supplier $supplier, SupplierPerformanceService $performance)
    {
        $supplier->load(['categories', 'products' => fn ($q) => $q->orderBy('name')]);

        $recentOrders = $supplier->purchaseOrders()
            ->with(['createdBy', 'approvedBy'])
            ->latest()
            ->take(5)
            ->get();

        return view('suppliers.show', [
            'supplier' => $supplier,
            'metrics' => $performance->metrics($supplier),
            'recentOrders' => $recentOrders,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', [
            'supplier' => $supplier,
            'categories' => Category::active()->orderBy('name')->get(),
            'paymentTerms' => Supplier::paymentTermsOptions(),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateSupplier($request, $supplier);
        $data['is_active'] = $request->boolean('is_active');
        $categories = $data['categories'] ?? [];
        unset($data['categories']);

        DB::transaction(function () use ($supplier, $data, $categories) {
            $supplier->update($data);
            $supplier->categories()->sync($categories);
        });

        ActivityLogger::log('updated', 'Suppliers', "Updated supplier '{$supplier->company_name}'.");

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier details updated.');
    }

    /** Soft delete, guarded by open purchase orders. */
    public function destroy(Supplier $supplier)
    {
        $openOrders = $supplier->purchaseOrders()
            ->whereIn('status', [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_PENDING,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_SENT,
            ])
            ->count();

        if ($openOrders > 0) {
            return back()->with('error', "Cannot archive '{$supplier->company_name}': {$openOrders} open purchase order(s) still reference it.");
        }

        $supplier->delete();
        ActivityLogger::log('deactivated', 'Suppliers', "Soft-deleted supplier '{$supplier->company_name}'.");

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier archived. Historical purchase orders remain intact.');
    }

    public function restore(int $supplier)
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        $record->restore();

        ActivityLogger::log('restored', 'Suppliers', "Restored supplier '{$record->company_name}'.");

        return redirect()->route('suppliers.show', $record)
            ->with('success', 'Supplier restored and available for new orders.');
    }

    /** @return array<string, mixed> */
    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        $uniqueEmail = Rule::unique('suppliers', 'email');
        if ($supplier) {
            $uniqueEmail->ignore($supplier->id);
        }

        return $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', $uniqueEmail],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['required', Rule::in(Supplier::paymentTermsOptions())],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:90'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['sometimes', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
        ], [
            'email.unique' => 'Another supplier already uses this email address.',
            'phone.regex' => 'Enter a valid phone number (digits, +, -, spaces or parentheses).',
            'lead_time_days.min' => 'Lead time must be at least 1 day.',
        ]);
    }
}
