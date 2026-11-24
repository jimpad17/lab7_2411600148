@php
    $selectedSupplier = old('supplier_id', $order->supplier_id);
    $lineItems = old('items', $order->exists
        ? $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'notes' => $item->notes,
        ])->all()
        : []);
    $productPayload = $products->map(fn ($product) => [
        'id' => $product->id,
        'supplier_id' => $product->supplier_id,
        'name' => $product->name,
        'sku' => $product->sku,
        'price' => (float) $product->unit_price,
    ])->values()->all();
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="supplier_id" class="form-label small fw-bold">Supplier <span class="text-danger">*</span></label>
        <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
            <option value="">— Select supplier —</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) $selectedSupplier === (string) $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->company_name }} ({{ $supplier->lead_time_days }}d lead)
                </option>
            @endforeach
        </select>
        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="order_date" class="form-label small fw-bold">Order date <span class="text-danger">*</span></label>
        <input type="date" name="order_date" id="order_date" class="form-control @error('order_date') is-invalid @enderror"
               value="{{ old('order_date', $order->order_date?->format('Y-m-d') ?? today()->toDateString()) }}" required>
        @error('order_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="expected_delivery_date" class="form-label small fw-bold">Expected delivery</label>
        <input type="date" name="expected_delivery_date" id="expected_delivery_date" class="form-control @error('expected_delivery_date') is-invalid @enderror"
               value="{{ old('expected_delivery_date', $order->expected_delivery_date?->format('Y-m-d')) }}">
        @error('expected_delivery_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="terms" class="form-label small fw-bold">Terms</label>
        <input type="text" name="terms" id="terms" class="form-control" maxlength="100"
               value="{{ old('terms', $order->terms) }}" placeholder="e.g. Net 30">
    </div>
    <div class="col-md-6">
        <label for="notes" class="form-label small fw-bold">Notes</label>
        <input type="text" name="notes" id="notes" class="form-control" maxlength="1000"
               value="{{ old('notes', $order->notes) }}" placeholder="Optional instructions">
    </div>
</div>

<hr class="my-4">

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-list-check text-warning me-2"></i> Line Items</h5>
    <button type="button" class="btn btn-outline-primary btn-sm" id="add-item">
        <i class="bi bi-plus-circle me-1"></i> Add Item
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:45%">Product</th>
                <th style="width:12%">Quantity</th>
                <th style="width:15%">Unit price (₱)</th>
                <th style="width:15%">Line total</th>
                <th style="width:11%">Notes</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="items-body"></tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Subtotal</th>
                <th id="subtotal-display">₱0.00</th>
                <th colspan="2"></th>
            </tr>
            <tr>
                <th colspan="3" class="text-end">Tax (12%)</th>
                <th id="tax-display">₱0.00</th>
                <th colspan="2"></th>
            </tr>
            <tr class="table-warning">
                <th colspan="3" class="text-end">Total</th>
                <th id="total-display">₱0.00</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>
</div>

@error('items') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
@error('items.*.product_id') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
@error('items.*.quantity') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror

<div class="d-flex flex-wrap gap-2 mt-4">
    <button type="submit" name="submit_action" value="pending" class="btn btn-warning px-4 fw-bold">
        <i class="bi bi-send me-1"></i> {{ $order->exists ? 'Save Changes' : 'Submit for Approval' }}
    </button>
    @unless ($order->exists)
        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary px-4">
            <i class="bi bi-file-earmark me-1"></i> Save Draft
        </button>
    @endunless
    <a href="{{ $order->exists ? route('purchase-orders.show', $order) : route('purchase-orders.index') }}" class="btn btn-outline-danger">Cancel</a>
</div>

@push('scripts')
<script>
(function () {
    const products = @json($productPayload);
    const initialItems = @json($lineItems);
    const supplierSelect = document.getElementById('supplier_id');
    const body = document.getElementById('items-body');
    let rowIndex = 0;

    function availableProducts() {
        const supplierId = supplierSelect.value;
        return products.filter(p => !supplierId || String(p.supplier_id) === String(supplierId));
    }

    function productOptions(selectedId) {
        const options = ['<option value="">— Select product —</option>'];
        availableProducts().forEach(p => {
            const selected = String(p.id) === String(selectedId) ? ' selected' : '';
            options.push(`<option value="${p.id}"${selected}>${p.name} (${p.sku})</option>`);
        });
        return options.join('');
    }

    function recalculate() {
        let subtotal = 0;
        body.querySelectorAll('tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const line = qty * price;
            subtotal += line;
            row.querySelector('.line-total').textContent = '₱' + line.toFixed(2);
        });
        const tax = subtotal * 0.12;
        document.getElementById('subtotal-display').textContent = '₱' + subtotal.toFixed(2);
        document.getElementById('tax-display').textContent = '₱' + tax.toFixed(2);
        document.getElementById('total-display').textContent = '₱' + (subtotal + tax).toFixed(2);
    }

    function addRow(item) {
        item = item || { product_id: '', quantity: 1, unit_price: '', notes: '' };
        const product = products.find(p => String(p.id) === String(item.product_id));
        const price = item.unit_price || (product ? product.price : '');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select name="items[${rowIndex}][product_id]" class="form-select form-select-sm item-product" required>
                    ${productOptions(item.product_id)}
                </select>
            </td>
            <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control form-control-sm item-qty" min="1" value="${item.quantity || 1}" required></td>
            <td><input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_price]" class="form-control form-control-sm item-price" value="${price}" required></td>
            <td class="line-total fw-bold">₱0.00</td>
            <td><input type="text" name="items[${rowIndex}][notes]" class="form-control form-control-sm" maxlength="255" value="${item.notes || ''}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove"><i class="bi bi-x"></i></button></td>
        `;
        row.querySelector('.item-product').addEventListener('change', function () {
            const selected = products.find(p => String(p.id) === this.value);
            row.querySelector('.item-price').value = selected ? selected.price : '';
            recalculate();
        });
        row.querySelector('.remove-item').addEventListener('click', function () {
            if (body.querySelectorAll('tr').length > 1) {
                row.remove();
                recalculate();
            }
        });
        row.querySelector('.item-qty').addEventListener('input', recalculate);
        row.querySelector('.item-price').addEventListener('input', recalculate);
        body.appendChild(row);
        rowIndex++;
        recalculate();
    }

    document.getElementById('add-item').addEventListener('click', () => addRow());
    supplierSelect.addEventListener('change', function () {
        body.innerHTML = '';
        rowIndex = 0;
        addRow();
    });
    body.addEventListener('input', recalculate);

    if (initialItems.length) {
        initialItems.forEach(addRow);
    } else {
        addRow();
    }
})();
</script>
@endpush
