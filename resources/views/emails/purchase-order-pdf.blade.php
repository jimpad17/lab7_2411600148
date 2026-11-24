<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $order->reference_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
        h1 { font-size: 19px; margin: 0 0 2px; }
        .meta { color: #6c757d; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #212529; color: #fff; text-align: left; padding: 7px 6px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; }
        .summary { margin-top: 14px; }
        .summary td { border: 0; padding: 4px 0; }
        .total { font-size: 13px; font-weight: bold; color: #198754; }
    </style>
</head>
<body>
    <h1>Purchase Order {{ $order->reference_number }}</h1>
    <div class="meta">JimTrack Inventory · Generated {{ now()->format('M d, Y h:i A') }}</div>

    <table>
        <tr><th style="width:30%">Supplier</th><td>{{ $order->supplier?->company_name ?? '—' }}</td></tr>
        <tr><th>Order date</th><td>{{ $order->order_date?->format('M d, Y') ?? '—' }}</td></tr>
        <tr><th>Expected delivery</th><td>{{ $order->expected_delivery_date?->format('M d, Y') ?? 'Not set' }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($order->status) }}</td></tr>
    </table>

    <table style="margin-top:16px;">
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align:right">Qty</th>
                <th style="text-align:right">Unit price</th>
                <th style="text-align:right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                    <td style="text-align:right">{{ $item->quantity }}</td>
                    <td style="text-align:right">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td style="text-align:right">₱{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Subtotal</td><td style="text-align:right">₱{{ number_format((float) $order->subtotal, 2) }}</td></tr>
        <tr><td>Tax (12%)</td><td style="text-align:right">₱{{ number_format((float) $order->tax, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td style="text-align:right">₱{{ number_format((float) $order->total, 2) }}</td></tr>
    </table>

    @if ($order->notes)
        <p style="margin-top:16px;"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif
</body>
</html>
