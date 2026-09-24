@extends('pages.layout')

@section('title', 'Laravel Feature Kit - Professional Feature-Driven Starter Kit')
@section('description', 'Build production-grade Laravel 12 applications with Feature-Driven Architecture, React 19, Inertia.js v2, Tailwind CSS v4, and 40+ built-in utilities.')

@section('content')
    <!-- Hero Section -->
    <div class="relative bg-surface overflow-hidden border-b border-surface-foreground/10">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-surface sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-foreground sm:text-5xl md:text-6xl">
                            <span class="block xl:inline">Build your professional</span>
                            <span class="block text-primary xl:inline">Laravel Feature Kit</span>
                        </h1>
                        <p class="mt-3 text-base text-surface-foreground/60 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            A complete starter kit for building robust, scalable applications with Laravel 12, Inertia.js v2, and React 19. Feature-Driven Architecture, 11 JS utilities, 20+ React hooks, 7 Artisan scaffolders, and a universal mail system — all pre-configured.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href=""
                                   class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 md:py-4 md:text-lg md:px-10 transition-colors">
                                    Get Started
                                </a>
                            </div>
                            <div class="mt-3 sm:mt-0 sm:ml-3">
                                <a href="{{ route('landing.docs') }}"
                                   class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-primary bg-primary/10 hover:bg-primary/20 md:py-4 md:text-lg md:px-10 transition-colors">
                                    Documentation
                                </a>
                            </div>
                        </div>
                        <div class="mt-6">
                            <pre class="inline-block bg-surface-muted px-4 py-2 rounded-lg text-sm text-surface-muted-foreground font-mono border border-surface-foreground/10"><code>composer create-project rifatxtra/laravel-featurekit my-app</code></pre>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
            <div class="h-56 w-full bg-primary/5 sm:h-72 md:h-96 lg:w-full lg:h-full flex items-center justify-center">
                <!-- SVG Illustration Placeholder -->
                <svg class="h-2/3 w-2/3 text-primary/20" fill="currentColor" viewBox="0 0 200 200">
                    <path d="M43.3,25.1C57.4,6.1,81.3-4.4,101.4,1.4s36.5,24.3,42,46.1s-.4,47.1-13.5,66.1s-33.4,31.7-53.5,31.7s-40.4-12.7-53.5-31.7S29.2,44.1,43.3,25.1z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Core Pillars Feature Section -->
    <div class="py-24 bg-base">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-primary font-semibold tracking-wide uppercase">Core Pillars</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-foreground sm:text-4xl">
                    Built for Scale and Developer Happiness
                </p>
                <p class="mt-4 max-w-2xl text-xl text-surface-foreground/60 lg:mx-auto">
                    A professional-grade foundation that combines the best of Laravel 12 and React 19.
                </p>
            </div>

            <div class="mt-20">
                <dl class="space-y-10 md:space-y-0 md:grid md:grid-cols-3 md:gap-x-8 md:gap-y-12">
                    <!-- Feature 1: FDD -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">Feature-Driven Design</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            Stop the "God Folder" mess. Organize logic into self-contained domains with dedicated Controllers, Services, Models, Requests, Observers, Events, Exceptions, and auto-discovered Routes.
                        </dd>
                    </div>

                    <!-- Feature 2: Next.js Style Routing -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">Next.js Style Structure</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            Familiar folder-based routing and automatic persistent layout injection. Build powerful React 19 dashboards with the developer experience of Next.js App Router. 3 pre-built layouts included.
                        </dd>
                    </div>

                    <!-- Feature 3: Smart Mailing -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">Universal Mail Engine</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            One queued <code>GeneralMail</code> class handles every email in your app. Professional Markdown master template with logo, content card, and branded footer. No more repetitive mailable boilerplate.
                        </dd>
                    </div>

                    <!-- Feature 4: JS Utilities -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">11-Module Utility Suite</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            Production-ready JS helpers: Image Compression (+ responsive generation), Toast, Storage (with TTL), Clipboard, Date, Number/Currency (15+ currencies), String, Validation, Debounce/Throttle, and Web Vitals monitoring.
                        </dd>
                    </div>

                    <!-- Feature 5: Scaffolding -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">7 Artisan Scaffolders</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            Custom commands to generate entire Features (with optional role-based subfolders), Controllers, Services, Requests, Events, Exceptions, and Observers — all wired to the architecture automatically.
                        </dd>
                    </div>

                    <!-- Feature 6: Tailwind v4 -->
                    <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                        <dt>
                            <div class="absolute -top-4 left-6 flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.172-1.172a4 4 0 015.656 0l1.172 1.172a4 4 0 010 5.656l-1.172 1.172a4 4 0 01-5.656 0l-1.172-1.172a4 4 0 010-5.656z" />
                                </svg>
                            </div>
                            <p class="mt-8 text-xl leading-6 font-bold text-foreground">Tailwind CSS v4 Tokens</p>
                        </dt>
                        <dd class="mt-2 text-base text-surface-foreground/60">
                            Semantic @theme design tokens (primary, secondary, surface, error, success) using OKLCH color space. White-label ready — change one line to rebrand your entire app.
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="mt-16 text-center">
                <a href="{{ route('landing.features') }}"
                   class="inline-flex items-center px-6 py-3 text-primary font-semibold bg-primary/10 rounded-xl hover:bg-primary/20 transition-colors">
                    View all 12 features →
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Start Section -->
    <div class="py-20 bg-surface border-t border-surface-foreground/10">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-extrabold text-foreground mb-4">Get Started in Seconds</h2>
            <p class="text-lg text-surface-foreground/60 mb-8">One command to install. One command to run. That's it.</p>
            <pre class="bg-base p-6 rounded-2xl border border-surface-foreground/10 text-left text-primary overflow-x-auto shadow-inner text-sm"><code>composer create-project rifatxtra/laravel-featurekit my-app
cd my-app
composer setup    <span class="text-surface-foreground/30"># deps + .env + key + migrate + build</span>
composer dev      <span class="text-surface-foreground/30"># server + queue + logs + vite (all at once)</span></code></pre>
        </div>
    </div>
@endsection
