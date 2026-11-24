<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #212529; }
        h1 { font-size: 18px; color: #212529; margin: 0 0 4px; }
        .meta { color: #6c757d; margin-bottom: 14px; }
        .accent { color: #fd7e14; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #212529; color: #fff; text-align: left; padding: 7px 6px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8f9fa; }
        .footer { margin-top: 14px; color: #6c757d; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">JimTrack <span class="accent">Inventory</span> · Generated {{ $generatedAt }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ((array) $row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center;">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Lab 7 — Advanced MVC Architecture with Laravel · Automated Inventory Replenishment &amp; Supplier Management</div>
</body>
</html>
