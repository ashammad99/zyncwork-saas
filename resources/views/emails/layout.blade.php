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
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
        Built with passion for modern developers.
    </p>

    @if(isset($unsubscribe_url))
        <p style="margin-top: 10px;">
            <a href="{{ $unsubscribe_url }}" style="font-size: 12px; color: #9ca3af; text-decoration: underline;">
                Unsubscribe from these emails
            </a>
        </p>
    @endif

    <div style="margin-top: 15px; font-size: 12px; color: #d1d5db;">
        {{ config('app.address') ?? '123 Developer Lane, Tech City' }}
    </div>
</div>
</x-mail::message>
