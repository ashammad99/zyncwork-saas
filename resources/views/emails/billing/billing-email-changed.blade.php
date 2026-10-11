<div style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <h2 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 0; margin-bottom: 24px; line-height: 1.3;">
        {{ __('billing.email_changed.heading') }}
    </h2>

    <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
        {{ __('billing.email_changed.body', ['name' => $changedBy, 'company' => $companyName, 'email' => $newEmail]) }}
    </p>

    <div style="padding: 20px; background-color: #f9fafb; border-radius: 12px; margin-top: 32px;">
        <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.5;">
            {{ __('billing.email_changed.not_you', ['company' => $companyName]) }}
        </p>
    </div>
</div>
