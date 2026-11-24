@extends('layouts.app')

@section('title', 'Edit '.$order->reference_number)

@section('heading')
    Edit Draft <span class="text-warning fw-bold">{{ $order->reference_number }}</span>
@endsection

@section('content')
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i> Only draft purchase orders can be edited. Once submitted, use the approval workflow actions instead.
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('purchase-orders.update', $order) }}">
                @csrf
                @method('PUT')
                @include('purchase-orders._form', ['order' => $order, 'products' => $products, 'suppliers' => $suppliers])
            </form>
        </div>
    </div>
@endsection
