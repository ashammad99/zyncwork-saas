<div style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <h2 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 0; margin-bottom: 24px; line-height: 1.3;">
        {{ $title ?? 'Reset your password' }}
    </h2>

    <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
        {{ $body ?? 'We received a request to reset your password. No problem! Click the button below to secure your account.' }}
    </p>

    @if(isset($actionUrl))
        <div style="margin-top: 32px; margin-bottom: 32px; text-align: center;">
            <x-mail::button :url="$actionUrl" color="primary" style="background-color: #4f46e5; border-radius: 12px; font-weight: 600; padding: 12px 24px; width: 100%;">
                {{ $actionText ?? 'Reset My Password' }}
            </x-mail::button>
        </div>
    @endif

    <div style="padding: 20px; background-color: #f9fafb; border-radius: 12px; margin-top: 32px;">
        <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.5;">
            <strong>Quick Security Check:</strong> If you didn't request this change, you can safely ignore this email. Your password will remain unchanged.
        </p>
    </div>

    <p style="margin-top: 32px; color: #9ca3af; font-size: 14px; line-height: 1.5;">
        Alternatively, copy and paste this link into your browser:<br>
        <span style="word-break: break-all; color: #4f46e5;">{{ $actionUrl }}</span>
    </p>
</div>
