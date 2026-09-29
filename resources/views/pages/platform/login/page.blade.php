@extends('pages.auth.layout')

@section('title', 'Platform sign in · '.config('app.name'))
@section('app_name', config('app.name').' Platform')
@section('description', 'Staff console. Every sign-in and action here is recorded.')

@section('content')

{{-- No "remember me" and no password reset: platform accounts are managed with `php artisan platform:admin`. --}}
<div class="mb-8">
    <h1 class="text-2xl font-extrabold text-surface-foreground">Platform sign in</h1>
    <p class="mt-2 text-sm text-surface-muted-foreground">For {{ config('app.name') }} staff only.</p>
</div>

<form method="POST" action="{{ route('platform.login.attempt') }}" class="space-y-6">
    @csrf

    {{-- Email --}}
    <div>
        <label for="email" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
            Email address
        </label>

        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus
               class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
               {{ $errors->has('email') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface">

        @error('email')
            <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password --}}
    <div>
        <label for="password" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
            Password
        </label>

        <div class="relative">
            <input id="password" type="password" name="password" autocomplete="current-password" required
                   class="w-full px-5 py-4 pr-14 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
                   {{ $errors->has('password') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface">

            <button type="button"
                    onclick="togglePassword('password', this)"
                    class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-surface-muted-foreground font-semibold">
                Show
            </button>
        </div>

        @error('password')
            <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
        @enderror
    </div>

    {{-- Submit --}}
    <button type="submit"
            class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold">
        Sign In
    </button>
</form>

{{-- JS --}}
<script>
function togglePassword(id, el) {
    const input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";
        el.textContent = "Hide";
    } else {
        input.type = "password";
        el.textContent = "Show";
    }
}
</script>

@endsection
