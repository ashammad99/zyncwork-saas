@extends('pages.auth.layout')

@section('title', 'Recovery codes · '.config('app.name'))
@section('app_name', config('app.name').' Platform')
@section('description', 'Staff console. Every sign-in and action here is recorded.')

@section('content')

<div class="mb-8">
    <h1 class="text-2xl font-extrabold text-surface-foreground">Save your recovery codes</h1>
    <p class="mt-2 text-sm text-surface-muted-foreground">
        If you lose your authenticator, each of these codes signs you in once. Store them somewhere safe, such as a password manager.
        <strong class="text-surface-foreground">They are shown only this once.</strong>
    </p>
</div>

<ul class="grid grid-cols-2 gap-3 p-5 rounded-2xl bg-surface-muted font-mono text-surface-foreground select-all">
    @foreach ($codes as $code)
        <li>{{ $code }}</li>
    @endforeach
</ul>

<a href="{{ route('platform.tenants.index') }}"
   class="mt-8 block w-full py-4 text-center bg-primary text-primary-foreground rounded-2xl font-bold">
    I've saved them. Continue to the console
</a>

@endsection
