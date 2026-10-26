<x-mail::message>
{{-- Header with Logo --}}
<div style="text-align: center; margin-bottom: 30px;">
    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" style="width: 80px; height: auto; margin-bottom: 15px;">
    <h1 style="color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.025em;">
        {{ config('app.name') }}
    </h1>
</div>

{{-- Main Content Area --}}
<div style="background-color: #ffffff; border-radius: 16px; border: 1px solid #f3f4f6; padding: 20px; color: #374151;">
    @include($content_view)
</div>

{{-- Styled Footer --}}
<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
    <p style="font-size: 13px; color: #6b7280; line-height: 1.5;">
        {{ __('emails.layout.rights', ['year' => date('Y'), 'app' => config('app.name')]) }}
    </p>

    @if(isset($unsubscribe_url))
        <p style="margin-top: 10px;">
            <a href="{{ $unsubscribe_url }}" style="font-size: 12px; color: #9ca3af; text-decoration: underline;">
                {{ __('emails.layout.unsubscribe') }}
            </a>
        </p>
    @endif
</div>
</x-mail::message>
