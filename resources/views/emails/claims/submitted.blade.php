<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty claim received</title>
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
                            <p style="margin:0 0 8px;color:#8a6500;font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Claim received</p>
                            <h1 style="margin:0 0 16px;color:#0b2a63;font-size:26px;line-height:1.25;">We have received your claim</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">Thank you for contacting David Tyre Company. Our team will review your claim and notify you when its status changes.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e8f0;border-radius:10px;background-color:#f7f9fc;">
                                <tr>
                                    <td style="padding:18px 20px;border-bottom:1px solid #e3e8f0;">
                                        <p style="margin:0 0 5px;color:#667085;font-size:12px;font-weight:bold;letter-spacing:.7px;text-transform:uppercase;">Claim number</p>
                                        <p style="margin:0;color:#0b2a63;font-size:20px;font-weight:bold;">{{ $claimNumber }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 5px;color:#667085;font-size:12px;font-weight:bold;letter-spacing:.7px;text-transform:uppercase;">Current status</p>
                                        <p style="margin:0;color:#202124;font-size:16px;font-weight:bold;">Submitted — awaiting review</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 12px;font-size:14px;line-height:1.6;color:#4b5563;">This email confirms receipt only; it does not mean the claim has been approved.</p>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">To check the latest status, use DTC Code <strong style="color:#202124;">{{ $dtcCardNo }}</strong> on the Check Warranty page.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #e8ebef;background-color:#fafbfc;color:#737b87;font-size:12px;line-height:1.5;text-align:center;">
                            David Tyre Company
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
