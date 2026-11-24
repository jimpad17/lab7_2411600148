@extends('emails.layout')

@section('title', 'New Purchase Order '.$order->reference_number)

@section('content')
    <h2 style="margin:0 0 12px;font-size:21px;color:#fd7e14;">New Purchase Order Created</h2>
    <p style="margin:0 0 18px;font-size:14px;">Hello approver,</p>
    <p style="margin:0 0 18px;font-size:14px;">
        A new purchase order <strong>{{ $order->reference_number }}</strong> was submitted by
        <strong>{{ $order->createdBy?->name ?? 'System' }}</strong> and is awaiting your approval.
    </p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-bottom:18px;">
        <tr style="background:#f8f9fa;"><th align="left" style="width:35%;">Supplier</th><td>{{ $order->supplier?->company_name ?? '—' }}</td></tr>
        <tr><th align="left">Order date</th><td>{{ $order->order_date?->format('M d, Y') }}</td></tr>
        <tr style="background:#f8f9fa;"><th align="left">Expected delivery</th><td>{{ $order->expected_delivery_date?->format('M d, Y') ?? 'Not set' }}</td></tr>
        <tr><th align="left">Total amount</th><td style="font-weight:700;color:#198754;">₱{{ number_format((float) $order->total, 2) }}</td></tr>
    </table>

    <h3 style="font-size:15px;margin:0 0 8px;">Line items</h3>
    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:12px;border:1px solid #e5e7eb;">
        <thead><tr style="background:#212529;color:#ffffff;"><th align="left">Product</th><th align="right">Qty</th><th align="right">Unit price</th><th align="right">Line total</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                    <td align="right">{{ $item->quantity }}</td>
                    <td align="right">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td align="right">₱{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin:22px 0 0;text-align:center;">
        <a href="{{ $url }}" style="background:#fd7e14;color:#ffffff;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;">Review Purchase Order</a>
    </p>
@endsection
