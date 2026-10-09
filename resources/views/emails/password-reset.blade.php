<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your password</title>
</head>
<body style="margin:0;padding:0;background-color:#f2f6f3;color:#292d32;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background-color:#f2f6f3;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;background-color:#ffffff;border-radius:10px;">
                    <tr>
                        <td align="center" style="padding:28px 24px 24px;border-bottom:3px solid #3f7f3d;">
                            <img src="{{ $logoCid }}" alt="Fabellon Construction and Development Corporation" width="128" style="display:block;width:128px;max-width:100%;height:auto;margin:0 auto 16px;">
                            <strong style="font-size:18px;color:#292d32;">Fabellon Construction</strong><br>
                            <span style="font-size:13px;color:#62676e;">and Development Corporation</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="margin:0 0 16px;font-size:23px;line-height:1.3;color:#292d32;">Reset your password</h1>
                            <p style="margin:0;line-height:1.6;color:#3f4741;">We received a request to reset the password for your employee account. Use the button below to choose a new password. This link expires in 30 minutes.</p>
                            <p style="margin:28px 0;text-align:center;">
                                <a href="{{ $url }}" style="display:inline-block;padding:13px 22px;border-radius:6px;background-color:#3f7f3d;color:#ffffff;text-decoration:none;font-weight:bold;">Reset password</a>
                            </p>
                            <p style="margin:0 0 24px;line-height:1.6;color:#62676e;">If you did not request a password reset, you can ignore this email.</p>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#62676e;">If the button does not work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#326832;text-decoration:underline;word-break:break-all;">{{ $url }}</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
