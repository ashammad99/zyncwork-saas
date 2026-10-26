@extends('pages.site.layout')

@section('title', __('site.pricing.title'))
@section('description', __('site.pricing.description'))

@section('content')
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <h1 class="text-4xl font-extrabold tracking-tight text-foreground">{{ __('site.pricing.heading') }}</h1>
        <p class="mt-4 text-lg text-surface-foreground max-w-2xl">{{ __('site.pricing.intro') }}</p>

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            {{-- Days and seats as signup gives them (config billing.trial_days, the trial plan's included_seats). --}}
            <div class="p-8 rounded-xl bg-surface border border-border" data-plan="trial">
                <h2 class="text-xl font-bold text-foreground">{{ __('site.pricing.trial.name') }}</h2>
                <p class="mt-4 text-3xl font-extrabold text-foreground">{{ trans_choice('site.pricing.trial.length', $trialDays, ['days' => $trialDays]) }}</p>
                <ul class="mt-6 space-y-2 text-surface-foreground">
                    <li>{{ trans_choice('site.pricing.trial.seats', $trialSeats, ['seats' => $trialSeats]) }}</li>
                    <li>{{ __('site.pricing.trial.no_card') }}</li>
                </ul>
                <p class="mt-6 text-sm text-surface-foreground">{{ __('site.pricing.trial.after') }}</p>
                <a href="{{ route('register') }}" class="mt-8 inline-block px-6 py-3 rounded-lg bg-primary text-primary-foreground font-semibold hover:bg-primary/90">
                    {{ __('site.nav.start_trial') }}
                </a>
            </div>

            @foreach ($plans as $plan)
                <div class="p-8 rounded-xl bg-surface border border-border" data-plan="{{ $plan->key }}">
                    <h2 class="text-xl font-bold text-foreground">{{ $plan->name }}</h2>
                    {{-- price_amount is in minor units; two decimals fit every currency we'd price in (USD, EUR, GBP). --}}
                    <p class="mt-4 text-3xl font-extrabold text-foreground">
                        {{ __('site.pricing.price', ['amount' => number_format($plan->price_amount / 100, 2), 'currency' => $plan->currency]) }}
                    </p>
                    <p class="mt-1 text-surface-foreground">{{ __("site.pricing.per_seat.{$plan->interval}") }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-10 space-y-2 text-sm text-surface-foreground max-w-2xl">
            <p>{{ __('site.pricing.billing_note') }}</p>
            <p><a href="{{ route('landing.refunds') }}" class="text-primary hover:underline">{{ __('site.pricing.refunds_link') }}</a></p>
        </div>
    </section>
@endsection
