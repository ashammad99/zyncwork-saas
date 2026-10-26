@extends('pages.site.layout')

@section('title', __('site.home.title'))
@section('description', __('site.home.description'))

@section('content')
    <section class="bg-surface border-b border-border">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-foreground max-w-3xl">
                {{ __('site.home.hero.heading') }}
            </h1>
            <p class="mt-6 text-lg text-surface-foreground max-w-2xl">
                {{ __('site.home.hero.body') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="px-6 py-3 rounded-lg bg-primary text-primary-foreground font-semibold hover:bg-primary/90">
                    {{ __('site.nav.start_trial') }}
                </a>
                <a href="{{ route('landing.pricing') }}" class="px-6 py-3 rounded-lg bg-primary-surface text-primary-surface-foreground font-semibold">
                    {{ __('site.home.cta.pricing') }}
                </a>
            </div>
            <p class="mt-4 text-sm text-surface-foreground">
                {{ trans_choice('site.home.hero.trial', $trialDays, ['days' => $trialDays]) }}
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <h2 class="text-3xl font-bold text-foreground">{{ __('site.home.features_heading') }}</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (array_keys(__('site.home.features')) as $feature)
                <div class="p-6 rounded-xl bg-surface border border-border">
                    <h3 class="font-semibold text-foreground">{{ __("site.home.features.{$feature}.title") }}</h3>
                    <p class="mt-2 text-sm text-surface-foreground">{{ __("site.home.features.{$feature}.body") }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-secondary-surface border-t border-border">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 text-center">
            <h2 class="text-2xl font-bold text-foreground">{{ __('site.home.cta.heading') }}</h2>
            <p class="mt-3 text-secondary-surface-foreground">{{ __('site.home.cta.body') }}</p>
            <a href="{{ route('register') }}" class="mt-6 inline-block px-6 py-3 rounded-lg bg-primary text-primary-foreground font-semibold hover:bg-primary/90">
                {{ __('site.nav.start_trial') }}
            </a>
        </div>
    </section>
@endsection
