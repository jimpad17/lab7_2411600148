@extends('emails.layout')

@section('title', 'Purchase Order '.$order->reference_number.' sent')

@section('content')
    <h2 style="margin:0 0 12px;font-size:21px;color:#0d6efd;">Purchase Order Sent to Supplier</h2>
    <p style="margin:0 0 18px;font-size:14px;">Hello {{ $order->supplier?->contact_person ?? 'Supplier' }},</p>
    <p style="margin:0 0 18px;font-size:14px;">
        Please find our purchase order <strong>{{ $order->reference_number }}</strong> detailed below.
        Kindly acknowledge receipt and confirm the delivery schedule.
    </p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
        <tr style="background:#f8f9fa;"><th align="left" style="width:35%;">Order reference</th><td>{{ $order->reference_number }}</td></tr>
        <tr><th align="left">Order date</th><td>{{ $order->order_date?->format('M d, Y') }}</td></tr>
        <tr style="background:#f8f9fa;"><th align="left">Payment terms</th><td>{{ $order->supplier?->payment_terms ?? $order->terms ?? 'Net 30' }}</td></tr>
        <tr><th align="left">Expected delivery</th><td>{{ $order->expected_delivery_date?->format('M d, Y') ?? 'To be confirmed' }}</td></tr>
        <tr style="background:#f8f9fa;"><th align="left">Order total</th><td style="font-weight:700;">₱{{ number_format((float) $order->total, 2) }}</td></tr>
    </table>

    <h3 style="font-size:15px;margin:20px 0 8px;">Items ordered</h3>
    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:12px;border:1px solid #e5e7eb;">
        <thead><tr style="background:#212529;color:#ffffff;"><th align="left">Product</th><th align="right">Quantity</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                    <td align="right">{{ $item->quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
