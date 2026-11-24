@extends('layouts.app')

@section('title', 'Create Purchase Order')

@section('heading')
    Create <span class="text-warning fw-bold">Purchase Order</span>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('purchase-orders.store') }}">
                @csrf
                @include('purchase-orders._form', ['order' => $order, 'products' => $products, 'suppliers' => $suppliers])
            </form>
        </div>
    </div>
@endsection
