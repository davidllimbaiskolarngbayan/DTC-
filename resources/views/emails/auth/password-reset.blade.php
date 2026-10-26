<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your password</title>
</head>
<body style="margin:0;padding:24px 12px;background-color:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#202124;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;overflow:hidden;border:1px solid #e3e5e8;border-radius:14px;background-color:#ffffff;">
                    <tr>
                        <td align="center" style="padding:24px 28px;background-color:#0b2a63;border-bottom:4px solid #fbbd00;">
                            <img src="{{ isset($message) && is_object($message) && method_exists($message, 'embed') ? $message->embed(public_path('images/dtc-logo-email.png')) : asset('images/dtc-logo-email.png') }}" width="220" alt="David Tyre Company" style="display:block;width:100%;max-width:220px;height:auto;border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="margin:0 0 8px;color:#8a6500;font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Account security</p>
                            <h1 style="margin:0 0 16px;color:#0b2a63;font-size:26px;line-height:1.25;">Reset your password</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">Hello {{ $userName }}, we received a request to reset the password for your DTC staff account.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" bgcolor="#0b2a63" style="border-radius:6px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:13px 24px;border:1px solid #0b2a63;border-radius:6px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">Reset password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4b5563;">This password reset link expires in 30 minutes.</p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">If you did not request a password reset, no further action is required.</p>
                            <p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#4b5563;">Regards,</p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">{{ config('dtc.brand_name') }}</p>
                            <div style="padding-top:18px;border-top:1px solid #e8ebef;">
                                <p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:#737b87;">If the button does not work, copy and paste this link into your browser:</p>
                                <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;"><a href="{{ $url }}" style="color:#0b2a63;">{{ $url }}</a></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #e8ebef;background-color:#fafbfc;color:#737b87;font-size:12px;line-height:1.5;text-align:center;">
                            &copy; {{ now()->year }} {{ config('dtc.brand_name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
