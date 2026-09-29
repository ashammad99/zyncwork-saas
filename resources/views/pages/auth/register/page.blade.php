@extends('pages.auth.layout')

@section('title', 'Create your company account - '.config('app.name'))
@section('description', 'Create an account for your company. You will be its first admin and can invite your team next.')

@section('content')

<div class="mb-6 flex justify-between items-center">
    <a href="{{ url('/') }}" class="text-sm font-semibold text-primary hover:underline">
        ← Back to Home
    </a>
    <a href="{{ route('login') }}" class="text-sm font-semibold text-primary hover:underline">
        Already have an account? Sign in
    </a>
</div>

<form method="POST" action="{{ route('auth.register.store') }}" class="space-y-6">
    @csrf

    {{-- Honeypot: hidden from people, filled in by bots (SignupController discards those). --}}
    <div aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
        <label>Leave this field empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    @foreach ([
        ['company_name', 'Company name', 'text', 'Acme Inc.', 'organization'],
        ['name', 'Your name', 'text', 'Jane Doe', 'name'],
        ['email', 'Work email', 'email', 'name@example.com', 'email'],
    ] as [$field, $label, $type, $placeholder, $autocomplete])
        <div>
            <label for="{{ $field }}" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
                {{ $label }}
            </label>

            <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}" required
                   class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
                   {{ $errors->has($field) ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface"
                   placeholder="{{ $placeholder }}">

            @error($field)
                <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    @foreach ([['password', 'Password'], ['password_confirmation', 'Confirm password']] as [$field, $label])
        <div>
            <label for="{{ $field }}" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
                {{ $label }}
            </label>

            <input id="{{ $field }}" type="password" name="{{ $field }}" autocomplete="new-password" required
                   class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
                   {{ $errors->has($field) ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface"
                   placeholder="••••••••">

            @error($field)
                <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <button type="submit" class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold">
        Create account
    </button>
</form>

@endsection
