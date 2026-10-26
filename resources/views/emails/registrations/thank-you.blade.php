<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty registration confirmation</title>
</head>
<body style="margin:0;padding:24px 12px;background-color:#ffffff;font-family:Arial,sans-serif;color:#17191b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;border:1px solid #dedede;border-radius:4px;">
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 24px;text-align:center;">
                                <img src="{{ isset($message) && is_object($message) && method_exists($message, 'embed') ? $message->embed(public_path('images/dtc-logo.svg')) : asset('images/dtc-logo.svg') }}" width="280" alt="David Tyre Company" style="display:inline-block;width:100%;max-width:280px;height:auto;border:0;">
                            </p>
                            <p style="margin:0 0 12px;font-size:16px;">Dear {{ $data['firstname'] ?? 'customer' }},</p>
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.4;font-weight:400;">
                                Thank you for registering your tyres with {{ config('dtc.brand_name') }}.
                            </h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.5;">Your purchase is now covered.</p>

                            <h2 style="margin:0 0 12px;font-size:18px;">Your registration details</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f4;border-radius:14px;">
                                @foreach ([
                                    'Brand' => $data['brand'] ?? '',
                                    'Model' => $data['model'] ?? '',
                                    'Registration ID' => $data['vehicle_reg_id'] ?? '',
                                    'Mileage' => $data['mileage'] ?? '',
                                    'Quantity' => $data['quantity'] ?? '',
                                    'Date of purchase' => $data['date_of_purchase'] ?? '',
                                    'Tyre' => $data['tyre'] ?? '',
                                    'Tyre size' => $data['tyre_size'] ?? '',
                                    'Rim size' => $data['rim_size'] ?? '',
                                    'DTC Code' => $data['dtc_card_no'] ?? '',
                                    'State' => $data['state'] ?? '',
                                    'Store' => $data['store_name'] ?? '',
                                ] as $label => $value)
                                    <tr>
                                        <td width="155" valign="top" style="width:155px;padding:10px 8px 10px 18px;font-size:16px;line-height:1.45;">
                                            <strong>{{ $label }}</strong>
                                        </td>
                                        <td valign="top" style="padding:10px 18px 10px 8px;font-size:16px;line-height:1.45;color:#52677f;word-break:break-word;">
                                            {{ $value !== '' && $value !== null ? $value : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <p style="margin:22px 0 0;font-size:16px;line-height:1.5;">
                                If you have any questions about your warranty, please contact us. Thank you!
                            </p>
                            @if (! empty($unsubscribeUrl))
                                <p style="margin:18px 0 0;font-size:14px;">
                                    <a href="{{ $unsubscribeUrl }}" style="color:#1769c2;">Unsubscribe from marketing emails</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
