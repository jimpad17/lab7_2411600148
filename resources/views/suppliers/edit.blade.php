@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('heading')
    Edit <span class="text-warning fw-bold">Supplier</span>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                @csrf
                @method('PUT')
                @include('suppliers._form', ['supplier' => $supplier, 'categories' => $categories, 'paymentTerms' => $paymentTerms])
            </form>
        </div>
    </div>
@endsection
