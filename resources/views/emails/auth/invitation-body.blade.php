<div style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <h2 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 0; margin-bottom: 24px; line-height: 1.3;">
        Join {{ $companyName }}
    </h2>

    <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
        {{ $inviterName }} has invited you to join {{ $companyName }} on {{ config('app.name') }} as {{ $role }}.
        Click the button below to set up your account.
    </p>

    <div style="margin-top: 32px; margin-bottom: 32px; text-align: center;">
        <x-mail::button :url="$actionUrl" color="primary" style="background-color: #4f46e5; border-radius: 12px; font-weight: 600; padding: 12px 24px; width: 100%;">
            Accept invitation
        </x-mail::button>
    </div>

    <div style="padding: 20px; background-color: #f9fafb; border-radius: 12px; margin-top: 32px;">
        <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.5;">
            This invitation expires on {{ $expiresAt }} and can be used once. If you weren't expecting it, you can ignore this email.
        </p>
    </div>

    <p style="margin-top: 32px; color: #9ca3af; font-size: 14px; line-height: 1.5;">
        Alternatively, copy and paste this link into your browser:<br>
        <span style="word-break: break-all; color: #4f46e5;">{{ $actionUrl }}</span>
    </p>
</div>
