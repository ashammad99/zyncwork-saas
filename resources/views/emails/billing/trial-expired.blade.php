<div style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <h2 style="color: #111827; font-size: 22px; font-weight: 700; margin-top: 0; margin-bottom: 24px; line-height: 1.3;">
        {{ __('billing.trial_expired.heading') }}
    </h2>

    <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
        {{ __('billing.trial_expired.body', ['app' => config('app.name'), 'company' => $companyName, 'date' => $date]) }}
    </p>

    <div style="margin-top: 32px; margin-bottom: 32px; text-align: center;">
        <x-mail::button :url="$actionUrl" color="primary" style="background-color: #4f46e5; border-radius: 12px; font-weight: 600; padding: 12px 24px; width: 100%;">
            {{ __('billing.view_billing') }}
        </x-mail::button>
    </div>

    <div style="padding: 20px; background-color: #f9fafb; border-radius: 12px; margin-top: 32px;">
        <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.5;">
            {{ __('billing.trial_expired.after') }}
        </p>
    </div>
</div>
