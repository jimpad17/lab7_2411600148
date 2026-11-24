<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'JimTrack Notification')</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Arial,sans-serif;color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:95%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
                    <tr>
                        <td style="background:#212529;padding:18px 24px;color:#ffffff;">
                            <span style="font-size:20px;font-weight:700;">⚡ JimTrack <span style="color:#ffc107;">Inventory</span></span>
                            <div style="font-size:12px;color:#dee2e6;margin-top:4px;">Automated Procurement Notification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8f9fa;padding:14px 24px;font-size:11px;color:#6c757d;border-top:1px solid #e5e7eb;">
                            This is an automated message from the JimTrack Inventory Management System. Please do not reply directly.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
