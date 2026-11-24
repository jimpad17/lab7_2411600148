@extends('emails.layout')

@section('title', 'Automated Reorder Alert')

@section('content')
    <h2 style="margin:0 0 12px;font-size:21px;color:#fd7e14;">Automated Reorder Alert</h2>
    <p style="margin:0 0 18px;font-size:14px;">
        The scheduled inventory check found <strong>{{ $products->count() }}</strong>
        product(s) at or below their calculated reorder point on {{ now()->format('M d, Y h:i A') }}.
        {{ $purchaseOrdersGenerated > 0 ? $purchaseOrdersGenerated.' draft purchase order(s) were generated automatically.' : '' }}
    </p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:12px;border:1px solid #e5e7eb;">
        <thead><tr style="background:#212529;color:#ffffff;"><th align="left">Product</th><th align="left">Supplier</th><th align="right">On hand</th><th align="right">Reorder point</th></tr></thead>
        <tbody>
            @forelse ($products as $item)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td>{{ $item->name }} <small style="color:#6c757d;">({{ $item->sku }})</small></td>
                    <td>{{ $item->supplier?->company_name ?? '—' }}</td>
                    <td align="right" style="color:#dc3545;font-weight:700;">{{ $item->quantity }}</td>
                    <td align="right">{{ $item->reorder_point ?? $item->reorder_level }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#6c757d;">All products are above their reorder point.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin:22px 0 0;text-align:center;">
        <a href="{{ $url }}" style="background:#fd7e14;color:#ffffff;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;">Open Procurement Dashboard</a>
    </p>
@endsection
