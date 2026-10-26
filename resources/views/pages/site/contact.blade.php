@extends('pages.site.layout')

@section('title', __('site.contact.title'))
@section('description', __('site.contact.description'))

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
        <h1 class="text-4xl font-extrabold tracking-tight text-foreground">{{ __('site.contact.heading') }}</h1>
        <p class="mt-4 text-lg text-surface-foreground">{{ __('site.contact.body') }}</p>

        <dl class="mt-10 space-y-6" data-operator>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wider text-surface-foreground">{{ __('site.contact.email') }}</dt>
                <dd class="mt-1 text-lg"><a href="mailto:{{ config('site.operator.email') }}" class="text-primary hover:underline">{{ config('site.operator.email') }}</a></dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wider text-surface-foreground">{{ __('site.contact.company') }}</dt>
                <dd class="mt-1 text-lg text-foreground">{{ config('site.operator.name') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wider text-surface-foreground">{{ __('site.contact.address') }}</dt>
                <dd class="mt-1 text-lg text-foreground">{{ config('site.operator.address') }}</dd>
            </div>
        </dl>
    </section>
@endsection
