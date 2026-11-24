@extends('emails.layout')

@section('title', 'Purchase Order '.$order->reference_number.' approved')

@section('content')
    <h2 style="margin:0 0 12px;font-size:21px;color:#198754;">Purchase Order Approved ✓</h2>
    <p style="margin:0 0 18px;font-size:14px;">Hello {{ $order->createdBy?->name ?? 'there' }},</p>
    <p style="margin:0 0 18px;font-size:14px;">
        Your purchase order <strong>{{ $order->reference_number }}</strong> for
        <strong>{{ $order->supplier?->company_name ?? 'supplier' }}</strong> has been approved by
        <strong>{{ $order->approvedBy?->name ?? 'management' }}</strong> on
        {{ $order->approved_at?->format('M d, Y h:i A') }}.
    </p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:13px;background:#f8f9fa;margin-bottom:18px;">
        <tr><th align="left" style="width:35%;">Reference</th><td>{{ $order->reference_number }}</td></tr>
        <tr><th align="left">Approved total</th><td style="font-weight:700;color:#198754;">₱{{ number_format((float) $order->total, 2) }}</td></tr>
        <tr><th align="left">Expected delivery</th><td>{{ $order->expected_delivery_date?->format('M d, Y') ?? 'Not set' }}</td></tr>
    </table>

    @if ($order->notes)
        <p style="font-size:13px;color:#6c757d;"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <p style="margin:22px 0 0;text-align:center;">
        <a href="{{ $url }}" style="background:#198754;color:#ffffff;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;">View Purchase Order</a>
    </p>
@endsection
