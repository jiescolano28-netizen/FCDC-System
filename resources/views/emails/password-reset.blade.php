<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your password</title>
</head>
<body style="margin:0;padding:32px 12px;background:#f4f5f7;color:#292d32;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:10px;">
        <tr>
            <td style="padding:32px;text-align:center;border-bottom:3px solid #c9a227;">
                <img src="{{ asset('image/company-logo.png') }}" alt="Fabellon Construction and Development Corporation" width="100" style="display:block;width:100px;height:auto;margin:0 auto 16px;">
                <strong style="font-size:18px;color:#292d32;">Fabellon Construction</strong><br>
                <span style="font-size:13px;color:#62676e;">and Development Corporation</span>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <h1 style="margin:0 0 16px;font-size:23px;">Reset your password</h1>
                <p style="line-height:1.6;">We received a request to reset the password for your employee account. Use the button below to choose a new password. This link expires in 30 minutes.</p>
                <p style="margin:28px 0;text-align:center;">
                    <a href="{{ $url }}" style="display:inline-block;padding:13px 22px;border-radius:6px;background:#c9a227;color:#fff;text-decoration:none;font-weight:bold;">Reset password</a>
                </p>
                <p style="line-height:1.6;color:#62676e;">If you did not request a password reset, you can ignore this email.</p>
                <p style="font-size:12px;line-height:1.5;color:#777;word-break:break-all;">If the button does not work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#675315;">{{ $url }}</a></p>
            </td>
        </tr>
    </table>
</body>
</html>
