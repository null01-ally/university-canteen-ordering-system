<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify your ZPPSU Canteen email</title></head>
<body style="margin:0;padding:24px 12px;background:#FDFBF7;font-family:Arial,sans-serif;color:#292426;">
<table role="presentation" style="width:100%;max-width:600px;margin:0 auto;border-collapse:collapse;background:#FFFFFF;border:1px solid #E5E7EB;">
<tr><td style="padding:24px;background:#6B1D2F;color:#FFFFFF;font-size:22px;font-weight:bold;">ZPPSU CANTEEN</td></tr>
<tr><td style="padding:32px 24px;">
<h1 style="margin:0 0 20px;font-size:24px;color:#6B1D2F;">Verify your email address</h1>
<p style="font-size:15px;line-height:1.6;">Hello {{ $name }},</p>
<p style="font-size:15px;line-height:1.6;">Please verify your email to start ordering from the ZPPSU Canteen.</p>
<p style="margin:28px 0;"><a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#6B1D2F;color:#FFFFFF;text-decoration:none;font-size:15px;font-weight:bold;">Verify Email Address</a></p>
<p style="font-size:13px;line-height:1.6;color:#57534E;">This link expires in {{ config('auth.verification.expire', 60) }} minutes.</p>
<p style="font-size:13px;line-height:1.6;">If you did not create a ZPPSU Canteen account, no further action is required.</p>
<hr style="margin:24px 0;border:0;border-top:1px solid #E5E7EB;">
<p style="font-size:12px;line-height:1.6;color:#57534E;">If the button does not work, copy this link into your browser:</p>
<p style="font-size:12px;line-height:1.6;word-break:break-all;"><a href="{{ $verificationUrl }}" style="color:#6B1D2F;">{{ $verificationUrl }}</a></p>
</td></tr></table></body></html>
