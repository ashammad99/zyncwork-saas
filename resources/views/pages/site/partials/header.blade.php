<header class="bg-surface border-b border-border">
    <nav class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('landing.index') }}" class="flex items-center gap-3">
            <img src="{{ asset('logo.png') }}" alt="" class="h-8 w-auto">
            <span class="text-lg font-bold text-foreground">{{ config('app.name') }}</span>
        </a>

        <div class="flex flex-wrap items-center gap-5 text-sm font-medium">
            <a href="{{ route('landing.pricing') }}" class="text-foreground hover:text-primary">{{ __('site.nav.pricing') }}</a>
            <a href="{{ route('landing.contact') }}" class="text-foreground hover:text-primary">{{ __('site.nav.contact') }}</a>

            @auth('web')
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">{{ __('site.nav.dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="text-foreground hover:text-primary">{{ __('site.nav.sign_in') }}</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">{{ __('site.nav.start_trial') }}</a>
            @endauth
        </div>
    </nav>
</header>
