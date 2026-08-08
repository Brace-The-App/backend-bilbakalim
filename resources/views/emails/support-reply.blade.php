<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bil Bakalım Destek</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0b1220 0%,#1a2744 55%,#243b5c 100%);padding:28px 28px 22px;text-align:center;">
                        <img src="{{ $logoUrl }}" alt="Bil Bakalım" width="140" style="display:inline-block;max-width:140px;height:auto;border:0;">
                        <div style="margin-top:14px;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.02em;">Bil Bakalım Destek</div>
                        <div style="margin-top:6px;color:rgba(255,255,255,.78);font-size:13px;">Talebinize yanıt</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px;">
                        <p style="margin:0 0 14px;font-size:15px;line-height:1.5;">
                            Merhaba{{ $ticket->name ? ' '.e($ticket->name) : '' }},
                        </p>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#475569;">
                            Destek talebinize (#{{ $ticket->id }}) yanıt verildi:
                        </p>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;font-size:15px;line-height:1.6;white-space:pre-wrap;word-break:break-word;">{{ $replyBody }}</div>
                        @if($ticket->subject || $ticket->message)
                            <div style="margin-top:22px;padding-top:18px;border-top:1px dashed #e2e8f0;">
                                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;margin-bottom:8px;">Orijinal talebiniz</div>
                                @if($ticket->subject)
                                    <div style="font-size:14px;font-weight:600;margin-bottom:6px;">{{ $ticket->subject }}</div>
                                @endif
                                <div style="font-size:13px;line-height:1.5;color:#64748b;white-space:pre-wrap;word-break:break-word;">{{ \Illuminate\Support\Str::limit((string) $ticket->message, 600) }}</div>
                            </div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 28px;">
                        <p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#64748b;">
                            Saygılarımızla,<br>
                            <strong style="color:#0f172a;">{{ $account['from_name'] ?? 'Bil Bakalım Destek' }}</strong>
                            @if(!empty($adminName))
                                <span style="color:#94a3b8;"> · {{ $adminName }}</span>
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8fafc;padding:16px 28px;text-align:center;border-top:1px solid #e2e8f0;">
                        <div style="font-size:12px;color:#94a3b8;line-height:1.45;">
                            Bu e-posta Bil Bakalım destek talebinize yanıt olarak gönderilmiştir.<br>
                            Yanıtlamak için bu maili yanıtlayabilirsiniz.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
