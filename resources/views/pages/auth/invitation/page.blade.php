@extends('pages.auth.layout')

@section('title', 'Accept your invitation - '.config('app.name'))
@section('description', 'Set up your account to join your team.')

@section('content')

@if (! $invitation)
    <div class="space-y-6">
        <p class="font-semibold text-surface-foreground">This invitation is no longer valid.</p>
        <p class="text-sm text-surface-muted-foreground">
            It may have expired, been used already, or been withdrawn. Ask your company admin to send you a new one.
        </p>
        <a href="{{ route('login') }}" class="text-sm font-semibold text-primary hover:underline">Go to sign in</a>
    </div>
@else
    <p class="mb-6 font-semibold text-surface-foreground">
        You're invited to join <strong>{{ $companyName }}</strong> as {{ $role }}.
    </p>

    <form method="POST" action="{{ route('invitations.accept', $token) }}" class="space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">Email address</label>
            <input type="email" value="{{ $invitation->email }}" disabled
                   class="w-full px-5 py-4 border-2 rounded-2xl outline-none border-surface-muted bg-surface">
            @error('email')
                <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="name" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">Your name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required
                   class="w-full px-5 py-4 border-2 rounded-2xl focus:ring-4 focus:ring-primary/10 outline-none
                   {{ $errors->has('name') ? 'border-error' : 'border-surface-muted focus:border-primary' }} bg-surface">
            @error('name')
                <p class="mt-2 text-sm text-error font-bold">{{ $message }}</p>
            @enderror
        </div>

        @foreach ([['password', 'Password'], ['password_confirmation', 'Confirm password']] as [$field, $label])
            <div>
                <label for="{{ $field }}" class="block text-sm font-bold text-surface-foreground mb-2 uppercase tracking-wider">{{ $label }}</label>
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
            Join {{ $companyName }}
        </button>
    </form>
@endif

@endsection
