<x-mail::message>
# {{ $title ?? 'Notification' }}

{{ $body }}

@if(isset($actionUrl))
<x-mail::button :url="$actionUrl">
{{ $actionText ?? 'Click Here' }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
