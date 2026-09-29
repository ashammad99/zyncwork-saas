@extends('pages.auth.layout')

@section('title', 'Verify your email - '.config('app.name'))
@section('description', 'One more step: confirm your email address to start using your account.')

@section('content')

<div class="space-y-6">
    <p class="font-semibold text-surface-foreground">
        We sent a verification link to <strong>{{ $email }}</strong>.
    </p>

    <p class="text-sm text-surface-muted-foreground">
        Click the link in that email to activate your account. Until then, you can't use the rest of the app.
        The link expires after {{ $expiresInMinutes }} minutes.
    </p>

    @if (session('status'))
        <p class="text-sm font-bold text-primary">{{ session('status') }}</p>
    @endif

    @error('email')
        <p class="text-sm text-error font-bold">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="w-full py-4 bg-primary text-primary-foreground rounded-2xl font-bold">
            Send a new link
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full text-sm font-semibold text-primary hover:underline">
            Sign out
        </button>
    </form>
</div>

@endsection
