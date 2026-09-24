@extends('pages.auth.layout')
@section('content')

{{-- Back to Home --}}
<div class="mb-6 flex justify-between items-center">
    <a href="{{ url('/') }}"
       class="text-sm font-semibold text-primary hover:underline">
        ← Back to Home
    </a>
</div>

<div class="mb-10 text-center">
    <h1 class="text-3xl font-extrabold text-surface-foreground mb-4">Reset Password</h1>
    <p class="text-surface-muted-foreground font-medium">
        Enter your email address and we'll send you a link to reset your password.
    </p>
</div>

<form method="POST" action="{{ route('auth.forgot-password.send') }}" class="space-y-6">
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

    {{-- Submit --}}
    <button type="submit"
            class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold transition-all hover:opacity-90 active:scale-[0.98]">
        Send Reset Link
    </button>
</form>

<div class="pt-8 text-center border-t border-border mt-10">
    <p class="text-surface-muted-foreground font-semibold">
        Remember your password?
        <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">
            Back to Login
        </a>
    </p>
</div>
@endsection