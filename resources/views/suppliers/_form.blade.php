<div class="row g-3">
    <div class="col-md-7">
        <label for="company_name" class="form-label small fw-bold">Company name <span class="text-danger">*</span></label>
        <input type="text" name="company_name" id="company_name" class="form-control @error('company_name') is-invalid @enderror"
               value="{{ old('company_name', $supplier->company_name) }}" required maxlength="150">
        @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label for="contact_person" class="form-label small fw-bold">Contact person</label>
        <input type="text" name="contact_person" id="contact_person" class="form-control @error('contact_person') is-invalid @enderror"
               value="{{ old('contact_person', $supplier->contact_person) }}" maxlength="100">
        @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $supplier->email) }}" required maxlength="150">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="phone" class="form-label small fw-bold">Phone</label>
        <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $supplier->phone) }}" placeholder="02-1234-5678" maxlength="30">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="address" class="form-label small fw-bold">Address</label>
        <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror"
               value="{{ old('address', $supplier->address) }}" maxlength="255">
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="payment_terms" class="form-label small fw-bold">Payment terms</label>
        <select name="payment_terms" id="payment_terms" class="form-select @error('payment_terms') is-invalid @enderror">
            @foreach ($paymentTerms as $term)
                <option value="{{ $term }}" {{ old('payment_terms', $supplier->payment_terms) === $term ? 'selected' : '' }}>{{ $term }}</option>
            @endforeach
        </select>
        @error('payment_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="lead_time_days" class="form-label small fw-bold">Lead time (days) <span class="text-danger">*</span></label>
        <input type="number" name="lead_time_days" id="lead_time_days" class="form-control @error('lead_time_days') is-invalid @enderror"
               value="{{ old('lead_time_days', $supplier->lead_time_days ?? 7) }}" min="1" max="90" required>
        <div class="form-text">Used by the reorder point calculator.</div>
        @error('lead_time_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="minimum_order_amount" class="form-label small fw-bold">Minimum order (₱)</label>
        <input type="number" name="minimum_order_amount" id="minimum_order_amount" step="0.01" min="0"
               class="form-control @error('minimum_order_amount') is-invalid @enderror"
               value="{{ old('minimum_order_amount', $supplier->minimum_order_amount ?? 0) }}">
        @error('minimum_order_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-12">
        <label class="form-label small fw-bold">Supply categories</label>
        <div class="d-flex flex-wrap gap-3">
            @forelse ($categories as $category)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}"
                           id="category-{{ $category->id }}"
                           {{ in_array($category->id, old('categories', $supplier->exists ? $supplier->categories->pluck('id')->all() : [])) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="category-{{ $category->id }}">{{ $category->name }}</label>
                </div>
            @empty
                <small class="text-muted">No categories available.</small>
            @endforelse
        </div>
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                   {{ old('is_active', $supplier->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label small fw-bold" for="is_active">Active supplier</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-warning px-4 fw-bold"><i class="bi bi-check2-circle me-1"></i> {{ $supplier->exists ? 'Save Changes' : 'Add Supplier' }}</button>
    <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
