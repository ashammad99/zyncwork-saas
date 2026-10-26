{{-- On every public page and the tenant auth pages (not on the platform host, where these routes 404). --}}
<footer class="bg-surface border-t border-border" aria-labelledby="legal-footer-heading">
    <h2 id="legal-footer-heading" class="sr-only">{{ __('site.footer.heading') }}</h2>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between text-sm">
        <ul class="flex flex-wrap gap-x-6 gap-y-2">
            <li><a href="{{ route('landing.pricing') }}" class="text-surface-foreground hover:text-primary">{{ __('site.nav.pricing') }}</a></li>
            <li><a href="{{ route('landing.terms') }}" class="text-surface-foreground hover:text-primary">{{ __('site.footer.terms') }}</a></li>
            <li><a href="{{ route('landing.privacy') }}" class="text-surface-foreground hover:text-primary">{{ __('site.footer.privacy') }}</a></li>
            <li><a href="{{ route('landing.refunds') }}" class="text-surface-foreground hover:text-primary">{{ __('site.footer.refunds') }}</a></li>
            <li><a href="{{ route('landing.contact') }}" class="text-surface-foreground hover:text-primary">{{ __('site.nav.contact') }}</a></li>
        </ul>
        <p class="text-surface-foreground/70">
            {{ __('site.footer.copyright', ['year' => date('Y'), 'operator' => config('site.operator.name')]) }}
        </p>
    </div>
</footer>
