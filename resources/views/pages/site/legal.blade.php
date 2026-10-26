@extends('pages.site.layout')

@section('title', __("site.legal.{$document}.title"))
@section('description', __("site.legal.{$document}.description"))

@section('content')
    <article class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
        <h1 class="text-4xl font-extrabold tracking-tight text-foreground">{{ __("site.legal.{$document}.title") }}</h1>

        {{-- Named on every legal page, whatever the document itself says. --}}
        <div class="mt-6 p-4 rounded-lg bg-surface border border-border text-sm text-surface-foreground" data-operator>
            <p class="font-semibold text-foreground">{{ __('site.operated_by', ['name' => config('site.operator.name')]) }}</p>
            <p>{{ config('site.operator.address') }}</p>
            <p><a href="mailto:{{ config('site.operator.email') }}" class="text-primary hover:underline">{{ config('site.operator.email') }}</a></p>
        </div>

        @if ($isPlaceholder)
            <p class="mt-6 p-4 rounded-lg border-2 border-error text-error font-semibold" data-placeholder>{{ __('site.legal.placeholder') }}</p>
        @endif

        {{-- Rendered by LegalDocumentService: HTML in the file is escaped, unsafe links dropped. --}}
        <div class="mt-8 text-surface-foreground leading-relaxed space-y-4
                    [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-foreground [&_h1]:mt-8
                    [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-foreground [&_h2]:mt-8
                    [&_h3]:font-semibold [&_h3]:text-foreground [&_h3]:mt-6
                    [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6
                    [&_a]:text-primary [&_a]:underline
                    [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4">
            {!! $html !!}
        </div>
    </article>
@endsection
