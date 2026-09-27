@extends('pages.auth.layout')

@section('title', 'Two-factor authentication · '.config('app.name'))
@section('app_name', config('app.name').' Platform')
@section('description', 'Staff console. Every sign-in and action here is recorded.')

@section('content')

<div class="mb-8">
    <h1 class="text-2xl font-extrabold text-surface-foreground">Two-factor authentication</h1>
    <p class="mt-2 text-sm text-surface-muted-foreground">Enter the 6-digit code from your authenticator app.</p>
</div>

<form method="POST" action="{{ route('platform.two-factor.verify') }}" class="space-y-6">
    @csrf

    <div>
        <label for="code" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
            Authentication code
        </label>

        <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" autofocus
               class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none tracking-[0.3em] text-lg
               {{ $errors->has('code') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface">

        @error('code')
            <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
        @enderror
    </div>

    <details class="group" @if ($errors->has('recovery_code')) open @endif>
        <summary class="cursor-pointer text-sm font-bold text-primary hover:underline">
            Lost your device? Use a recovery code
        </summary>

        <div class="mt-4">
            <label for="recovery_code" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
                Recovery code
            </label>

            <input id="recovery_code" type="text" name="recovery_code" autocomplete="off" maxlength="20"
                   class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none uppercase
                   {{ $errors->has('recovery_code') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface"
                   placeholder="XXXXX-XXXXX">

            <p class="mt-2 text-xs text-surface-muted-foreground">Each recovery code works once.</p>

            @error('recovery_code')
                <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
            @enderror
        </div>
    </details>

    <button type="submit" class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold">
        Verify
    </button>
</form>

<p class="mt-6 text-sm">
    <a href="{{ route('platform.login') }}" class="font-semibold text-primary hover:underline">← Back to sign in</a>
</p>

@endsection
