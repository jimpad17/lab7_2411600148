@extends('layouts.app')

@section('title', 'Add Supplier')

@section('heading')
    Add New <span class="text-warning fw-bold">Supplier</span>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('suppliers.store') }}">
                @csrf
                @include('suppliers._form', ['supplier' => $supplier, 'categories' => $categories, 'paymentTerms' => $paymentTerms])
            </form>
        </div>
    </div>
@endsection
