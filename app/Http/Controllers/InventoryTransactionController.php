<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exports\InventoryTransactionsExport;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Rules\SufficientStock;
use App\Services\ActivityLogger;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryTransactionController extends Controller
{
    /** Filterable, paginated audit list of every stock movement. */
    public function index(Request $request)
    {
        $filters = array_merge([
            'product_id' => '', 'type' => '', 'from' => '', 'to' => '',
            'reference' => '', 'include_reversed' => false,
        ], $request->only(['product_id', 'type', 'from', 'to', 'reference', 'include_reversed']));

        // Lab 7: the same SearchService powers the page and both exports, so a
        // filtered table always downloads exactly the rows being displayed.
        $searchFilters = [
            'product_id' => $filters['product_id'],
            'type' => $filters['type'],
            'user_id' => $request->input('user_id'),
            'from' => $filters['from'],
            'to' => $filters['to'],
            'search' => $filters['reference'],
            'include_reversed' => $filters['include_reversed'],
        ];

        $transactions = app(SearchService::class)
            ->transactions($searchFilters)
            ->paginate(20)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'types' => InventoryTransaction::typeOptions(),
            'filters' => $filters,
            'totalIn' => (int) InventoryTransaction::stockIn()->sum('quantity'),
            'totalOut' => (int) InventoryTransaction::stockOut()->sum('quantity'),
            'adjustments' => (int) InventoryTransaction::adjustments()->count(),
            'todayCount' => (int) InventoryTransaction::whereDate('created_at', today())->count(),
        ]);
    }

    public function create()
    {
        $products = Product::active()->with('brand')->orderBy('name')->get();

        return view('transactions.create', [
            'products' => $products,
            'productStocks' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'quantity' => (int) $p->quantity,
                'reorder' => (int) $p->reorder_level,
            ])->values(),
            'types' => InventoryTransaction::typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'type' => ['required', Rule::in(array_keys(InventoryTransaction::typeOptions()))],
            'direction' => [
                Rule::requiredIf($request->input('type') === InventoryTransaction::TYPE_ADJUSTMENT),
                'nullable',
                Rule::in([InventoryTransaction::DIRECTION_IN, InventoryTransaction::DIRECTION_OUT]),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000', new SufficientStock()],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'product_id.required' => 'Please choose a product.',
            'type.required' => 'Choose a transaction type.',
            'direction.required' => 'Choose whether the adjustment adds or removes stock.',
            'quantity.required' => 'Enter the number of units moved.',
            'quantity.min' => 'Quantity must be at least 1.',
        ]);

        $data['reference_number'] = $data['reference_number']
            ? strtoupper(strip_tags(trim($data['reference_number'])))
            : null;
        $data['notes'] = isset($data['notes']) ? strip_tags(trim($data['notes'])) : null;
        $data['user_id'] = auth()->id();

        // If the observer rejects the movement, DB::transaction rolls back the
        // transaction row as well - there is never a half-written movement.
        try {
            $transaction = DB::transaction(fn () => InventoryTransaction::create($data));
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        ActivityLogger::log('created', 'Inventory Transactions',
            "Recorded {$transaction->type_label} of {$transaction->quantity} pcs for '{$transaction->product->name}'.");

        return redirect()->route('transactions.show', $transaction)
            ->with('success', 'Stock movement recorded and inventory updated in real time.');
    }

    public function show(InventoryTransaction $transaction)
    {
        $transaction->load(['product.brand', 'product.category', 'user']);

        $history = InventoryTransaction::forProduct($transaction->product_id)
            ->with('user')
            ->where('id', '<=', $transaction->id)
            ->orderBy('id')
            ->get(['id', 'type', 'direction', 'quantity', 'balance_after', 'reference_number', 'user_id', 'created_at']);

        return view('transactions.show', [
            'transaction' => $transaction,
            'history' => $history,
        ]);
    }

    /**
     * Admin-only: reversing a movement creates an equal and opposite
     * transaction and marks the original as reversed. Nothing is deleted, so
     * the audit trail stays complete (same rule as Lab 5 master data).
     */
    public function destroy(InventoryTransaction $transaction)
    {
        if ($transaction->is_reversed) {
            return back()->with('error', 'This transaction has already been reversed.');
        }

        try {
            DB::transaction(function () use ($transaction): void {
                $reverseType = match ($transaction->type) {
                    InventoryTransaction::TYPE_IN => InventoryTransaction::TYPE_OUT,
                    InventoryTransaction::TYPE_OUT => InventoryTransaction::TYPE_IN,
                    default => InventoryTransaction::TYPE_ADJUSTMENT,
                };

                $direction = $reverseType === InventoryTransaction::TYPE_ADJUSTMENT
                    ? ($transaction->direction === InventoryTransaction::DIRECTION_OUT
                        ? InventoryTransaction::DIRECTION_IN
                        : InventoryTransaction::DIRECTION_OUT)
                    : $reverseType;

                InventoryTransaction::create([
                    'product_id' => $transaction->product_id,
                    'type' => $reverseType,
                    'direction' => $direction,
                    'quantity' => $transaction->quantity,
                    'reference_number' => 'REVERSAL-'.$transaction->id,
                    'notes' => "Reversal of transaction #{$transaction->id} ({$transaction->reference_number}).",
                    'user_id' => auth()->id(),
                ]);

                $transaction->updateQuietly(['is_reversed' => true]);
            });
        } catch (InsufficientStockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        ActivityLogger::log('updated', 'Inventory Transactions',
            "Reversed transaction #{$transaction->id} for '{$transaction->product->name}'.");

        return redirect()->route('transactions.index')
            ->with('success', "Transaction #{$transaction->id} was reversed. The audit record was kept.");
    }

    /** Lab 7: filtered stock-movement exports. */
    public function exportCsv(Request $request, InventoryTransactionsExport $exports)
    {
        return $exports->withFilters($this->exportFilters($request))->csv();
    }

    public function exportPdf(Request $request, InventoryTransactionsExport $exports)
    {
        return $exports->withFilters($this->exportFilters($request))->pdf();
    }

    /** @return array<string, mixed> */
    private function exportFilters(Request $request): array
    {
        return [
            'product_id' => $request->input('product_id'),
            'type' => $request->input('type'),
            'user_id' => $request->input('user_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'search' => $request->input('reference', $request->input('search')),
            'include_reversed' => $request->boolean('include_reversed'),
        ];
    }
}
