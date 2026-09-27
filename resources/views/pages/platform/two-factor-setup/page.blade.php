@extends('pages.auth.layout')

@section('title', 'Set up two-factor authentication · '.config('app.name'))
@section('app_name', config('app.name').' Platform')
@section('description', 'Staff console. Every sign-in and action here is recorded.')

@section('content')

<div class="mb-8">
    <h1 class="text-2xl font-extrabold text-surface-foreground">Set up two-factor authentication</h1>
    <p class="mt-2 text-sm text-surface-muted-foreground">
        Platform accounts require an authenticator app. Scan the code, then enter the 6-digit code it shows to finish signing in.
    </p>
</div>

<div class="flex flex-col sm:flex-row gap-6 items-start mb-8">
    {{-- Generated on the server from the otpauth:// URI; the secret never leaves this page. --}}
    <div class="p-3 bg-white border-2 border-surface-muted rounded-2xl shrink-0" aria-label="QR code for your authenticator app">
        {!! $qrCode !!}
    </div>

    <div class="min-w-0">
        <p class="text-sm font-bold text-surface-foreground uppercase tracking-wider mb-2">Can't scan it?</p>
        <p class="text-sm text-surface-muted-foreground mb-2">Enter this key in your app instead:</p>
        <code class="block p-3 rounded-xl bg-surface-muted text-surface-foreground font-mono text-sm break-all select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
    </div>
</div>

<form method="POST" action="{{ route('platform.two-factor.enable') }}" class="space-y-6">
    @csrf

    <div>
        <label for="code" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
            Code from your app
        </label>

        <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required autofocus
               class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none tracking-[0.3em] text-lg
               {{ $errors->has('code') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface">

        @error('code')
            <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold">
        Confirm and sign in
    </button>
</form>

<p class="mt-6 text-sm">
    <a href="{{ route('platform.login') }}" class="font-semibold text-primary hover:underline">← Back to sign in</a>
</p>

@endsection
