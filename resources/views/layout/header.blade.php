<header class="bg-surface border-b border-surface-foreground/10 sticky top-0 z-50">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" aria-label="Top">
        <div class="w-full py-4 flex items-center justify-between border-b border-primary lg:border-none">
            <div class="flex items-center">
                <a href="/">
                    <span class="sr-only">Laravel Feature Kit</span>
                    <img class="h-10 w-auto" src="{{ asset('logo.png') }}" alt="Logo">
                </a>
                <div class="hidden ml-10 space-x-8 lg:flex items-center">
                    <a href="{{ route('landing.features') }}" class="text-base font-medium text-foreground hover:text-primary transition-colors">Features</a>
                    <a href="{{ route('landing.docs') }}" class="text-base font-medium text-foreground hover:text-primary transition-colors">Documentation</a>
                </div>
            </div>
            <div class="ml-10 space-x-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-block bg-primary py-2 px-4 border border-transparent rounded-md text-base font-medium text-white hover:bg-primary/90 transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="inline-block bg-transparent py-2 px-4 text-base font-medium text-foreground hover:text-primary">Sign in</a>
                @endauth
            </div>
        </div>
        <div class="py-4 flex flex-wrap justify-center space-x-6 lg:hidden border-t border-surface-foreground/5">
            <a href="/" class="text-xs font-semibold uppercase tracking-wider text-surface-foreground hover:text-primary">Home</a>
            <a href="{{ route('landing.docs') }}" class="text-xs font-semibold uppercase tracking-wider text-surface-foreground hover:text-primary">Docs</a>
            <a href="{{ route('landing.features') }}" class="text-xs font-semibold uppercase tracking-wider text-surface-foreground hover:text-primary">Features</a>
            <a href="https://rifatxtra.com" target="_blank" class="text-xs font-semibold uppercase tracking-wider text-surface-foreground hover:text-primary">Author</a>
        </div>
    </nav>
</header>
