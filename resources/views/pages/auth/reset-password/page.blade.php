@extends('pages.auth.layout')

@section('content')
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-extrabold text-foreground">
            Set new password
        </h2>
        <p class="mt-2 text-center text-sm text-surface-foreground/60">
            Please enter your new password below.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-surface py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-surface-foreground/10">
            <form class="space-y-6" action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-foreground">
                        Email address
                    </label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required
                               value="{{ $email ?? old('email') }}"
                               class="appearance-none block w-full px-3 py-2 border border-surface-foreground/20 rounded-md shadow-sm placeholder-surface-foreground/40 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm bg-surface text-foreground">
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-foreground">
                        New Password
                    </label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" required
                               class="appearance-none block w-full px-3 py-2 border border-surface-foreground/20 rounded-md shadow-sm placeholder-surface-foreground/40 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm bg-surface text-foreground">
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-foreground">
                        Confirm New Password
                    </label>
                    <div class="mt-1">
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               class="appearance-none block w-full px-3 py-2 border border-surface-foreground/20 rounded-md shadow-sm placeholder-surface-foreground/40 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm bg-surface text-foreground">
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
