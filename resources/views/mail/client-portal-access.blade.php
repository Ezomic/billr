<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Review your time entries</title>
</head>
<body style="font-family: sans-serif; color: #111; background: #f9f9f9; padding: 40px 0; margin: 0;">
<div style="max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px;">
    <p style="font-size: 18px; font-weight: 600; margin: 0 0 16px;">Hi {{ $client->name }},</p>
    <p style="color: #374151; margin: 0 0 16px;">
        Your freelancer has logged time entries for your projects and is requesting your approval before generating an invoice.
    </p>
    <p style="margin: 0 0 32px;">
        <a href="{{ $url }}"
           style="display: inline-block; background: #111; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 600;">
            Review &amp; approve time entries
        </a>
    </p>
    <p style="color: #6b7280; font-size: 13px; margin: 0;">
        This link is personal and does not require a password. Do not share it with others.
    </p>
</div>
</body>
</html>
