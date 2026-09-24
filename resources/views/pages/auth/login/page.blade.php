@extends('pages.auth.layout')

@section('content')

{{-- Back to Home --}}
<div class="mb-6 flex justify-between items-center">
    <a href="{{ url('/') }}"
       class="text-sm font-semibold text-primary hover:underline">
        ← Back to Home
    </a>
</div>



<form method="POST" action="{{ route('auth.login.login') }}" class="space-y-6">
    @csrf

    {{-- Email --}}
    <div>
        <label class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">
            Email address
        </label>

        <input type="email" name="email" value="{{ old('email') }}"
               class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
               {{ $errors->has('email') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface"
               placeholder="name@example.com">

        @error('email')
            <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password --}}
    <div>
        <div class="flex justify-between items-center mb-2">
            <label class="block text-sm font-bold text-surface-foreground uppercase tracking-wider">
                Password
            </label>

            <a href="{{ route('auth.forgot-password.index') }}"
               class="text-xs font-bold text-primary hover:underline">
                Forgot?
            </a>
        </div>

        <div class="relative">
            <input id="password"
                   type="password"
                   name="password"
                   class="w-full px-5 py-4 pr-14 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
                   {{ $errors->has('password') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface"
                   placeholder="••••••••">

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

    {{-- Remember --}}
    <div class="flex items-center">
        <input type="checkbox" name="remember" class="w-5 h-5 accent-primary">
        <label class="ml-3 font-semibold text-surface-muted-foreground">
            Keep me signed in
        </label>
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
