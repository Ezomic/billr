<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invitation to {{ $invitation->workspace->name ?? 'Billr' }}</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f9fafb;">
<div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb;">
    <div style="background: #111827; padding: 32px 40px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;">{{ $invitation->workspace->name ?? 'Billr' }}</h1>
    </div>
    <div style="padding: 40px;">
        <p style="margin-top: 0;">Hi,</p>
        <p>
            You have been invited to join
            <strong>{{ $invitation->workspace->name ?? 'a workspace' }}</strong> on Billr
            @if($invitation->role)
                as a {{ $invitation->role }}.
            @else
                .
            @endif
        </p>

        <p style="margin: 28px 0;">
            <a href="{{ $acceptUrl }}"
               style="display: inline-block; background: #111827; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
                Accept the invitation
            </a>
        </p>

        <p style="color: #6b7280; font-size: 13px;">
            If the button does not work, paste this into your browser:<br>
            <span style="word-break: break-all;">{{ $acceptUrl }}</span>
        </p>

        <p style="color: #6b7280; font-size: 13px; margin-bottom: 0;">
            This invitation expires on {{ $invitation->expires_at->format('d M Y') }}.
            If you were not expecting it, you can ignore this email.
        </p>
    </div>
    <div style="padding: 20px 40px; background: #f9fafb; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;">
        Sent by {{ $invitation->workspace->name ?? 'Billr' }} via Billr
    </div>
</div>
</body>
</html>
