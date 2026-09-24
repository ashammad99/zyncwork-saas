@extends('pages.layout')

@section('title', 'Features - Laravel Feature Kit')
@section('description', 'Discover every feature in Laravel FeatureKit v2.1.1. Feature-Driven Architecture, React 19, 20+ hooks, 11 utilities, 7 Artisan commands, and more.')

@section('content')
<div class="bg-base py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="text-center mb-16">
            <h1 class="text-4xl font-extrabold text-foreground sm:text-5xl md:text-6xl mb-6">
                Why choose <span class="text-primary">FeatureKit?</span>
            </h1>
            <p class="max-w-3xl mx-auto text-xl text-surface-foreground/60">
                A complete comparison showing how we solve the scaling, organization, and developer experience problems inherent in traditional Laravel architectures.
            </p>
        </div>

        {{-- Comparison Section --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-24">
            <div class="space-y-8">
                <div class="p-8 bg-surface rounded-3xl border border-surface-foreground/5 shadow-sm">
                    <h3 class="text-2xl font-bold text-foreground mb-4">Traditional Laravel</h3>
                    <ul class="space-y-4">
                        <li class="flex items-center text-surface-foreground/60">
                            <svg class="h-5 w-5 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            Scattered logic across app/Http, app/Models, app/Providers
                        </li>
                        <li class="flex items-center text-surface-foreground/60">
                            <svg class="h-5 w-5 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            Massive controllers with mixed business logic
                        </li>
                        <li class="flex items-center text-surface-foreground/60">
                            <svg class="h-5 w-5 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            Manual route registration and boilerplate
                        </li>
                        <li class="flex items-center text-surface-foreground/60">
                            <svg class="h-5 w-5 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            One mailable class per email type
                        </li>
                        <li class="flex items-center text-surface-foreground/60">
                            <svg class="h-5 w-5 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            No built-in frontend utilities or hooks
                        </li>
                    </ul>
                </div>
                <div class="p-8 bg-primary/5 rounded-3xl border border-primary/20 shadow-lg">
                    <h3 class="text-2xl font-bold text-primary mb-4">FeatureKit Design</h3>
                    <ul class="space-y-4">
                        <li class="flex items-center text-foreground font-medium">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Self-contained app/Features/{Name} domains
                        </li>
                        <li class="flex items-center text-foreground font-medium">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Thin Controllers + Domain Service pattern
                        </li>
                        <li class="flex items-center text-foreground font-medium">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Automatic route discovery per feature
                        </li>
                        <li class="flex items-center text-foreground font-medium">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Universal GeneralMail for all emails
                        </li>
                        <li class="flex items-center text-foreground font-medium">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            11 JS utilities + 20+ React hooks built-in
                        </li>
                    </ul>
                </div>
            </div>
            <div class="relative">
                <div class="aspect-square bg-gradient-to-tr from-primary to-primary/30 rounded-full opacity-10 absolute -inset-10 animate-pulse"></div>
                <div class="relative p-12 bg-surface rounded-3xl border border-surface-foreground/10 shadow-2xl">
                    <code class="text-sm text-primary">
                        <span class="block mb-2">// The Feature-Driven Future</span>
                        <span class="block opacity-60">app/Features/Payment/</span>
                        <span class="block ml-4 opacity-40">├── Controllers/</span>
                        <span class="block ml-4 opacity-40">├── Services/</span>
                        <span class="block ml-4 opacity-40">├── Models/</span>
                        <span class="block ml-4 opacity-40">├── Requests/</span>
                        <span class="block ml-4 opacity-40">├── Observers/</span>
                        <span class="block ml-4 opacity-40">├── Events/</span>
                        <span class="block ml-4 opacity-40">├── Exceptions/</span>
                        <span class="block ml-4 opacity-40">└── routes/</span>
                        <span class="block ml-8 opacity-30">├── web.php  (auto-discovered)</span>
                        <span class="block ml-8 opacity-30">└── api.php  (auto-discovered)</span>
                    </code>
                </div>
            </div>
        </div>

        {{-- Three Pillars --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center mb-24">
            <div class="p-10">
                <div class="text-5xl font-black text-primary mb-2">01</div>
                <h4 class="text-xl font-bold mb-2">Isolation</h4>
                <p class="text-surface-foreground/60">Deleting a feature is as easy as deleting its folder. No hunt for scattered files across dozens of directories.</p>
            </div>
            <div class="p-10">
                <div class="text-5xl font-black text-primary mb-2">02</div>
                <h4 class="text-xl font-bold mb-2">DX First</h4>
                <p class="text-surface-foreground/60">Next.js style frontend patterns, 7 artisan scaffolders, auto-layout injection, and a single dev command to start everything.</p>
            </div>
            <div class="p-10">
                <div class="text-5xl font-black text-primary mb-2">03</div>
                <h4 class="text-xl font-bold mb-2">Reliability</h4>
                <p class="text-surface-foreground/60">Standardized API traits, Base classes for controllers/services/exceptions, and unified error responses across your entire application.</p>
            </div>
        </div>

        {{-- Feature Cards Grid --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl font-extrabold text-foreground sm:text-4xl mb-4">Everything Included</h2>
            <p class="max-w-2xl mx-auto text-lg text-surface-foreground/60">
                A deep look at every system that ships with FeatureKit v2.1.1.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-24">
            {{-- Feature 1: FDD --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Feature-Driven Architecture</h3>
                <p class="text-sm text-surface-foreground/60">Self-contained domains with dedicated Controllers, Services, Models, Requests, Observers, Events, Exceptions, and auto-discovered Routes.</p>
            </div>

            {{-- Feature 2: Auto Route Discovery --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Auto Route Discovery</h3>
                <p class="text-sm text-surface-foreground/60">Routes in <code>app/Features/*/routes/</code> are scanned and registered at boot. Role-based features get automatic URL and name prefixes.</p>
            </div>

            {{-- Feature 3: Hybrid Frontend --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Hybrid Frontend Strategy</h3>
                <p class="text-sm text-surface-foreground/60">Blade + Tailwind v4 for SEO pages (landing, docs, auth). React 19 + Inertia.js v2 for SPA portals with Next.js App Router style folder structure.</p>
            </div>

            {{-- Feature 4: Auto Layouts --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Automatic Layout Injection</h3>
                <p class="text-sm text-surface-foreground/60">Every React page is automatically wrapped in MainLayout (Toast + Modal + Spinner) — zero configuration. Override per-page with AdminLayout or UserLayout.</p>
            </div>

            {{-- Feature 5: Mail System --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Universal Mail Engine</h3>
                <p class="text-sm text-surface-foreground/60">One <code>GeneralMail</code> class handles every email — queued by default, professional Markdown master layout with logo, content card, and branded footer.</p>
            </div>

            {{-- Feature 6: UI Kit --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.172-1.172a4 4 0 015.656 0l1.172 1.172a4 4 0 010 5.656l-1.172 1.172" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">7 React UI Components</h3>
                <p class="text-sm text-surface-foreground/60">Toast, Modal, LoadingSpinner, Pagination, SeoHead, BasicEditor (TipTap rich text), and PromoTemplates (5 marketing modal variants).</p>
            </div>

            {{-- Feature 7: JS Utilities --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">11-Module JS Utility Suite</h3>
                <p class="text-sm text-surface-foreground/60">Image Compression (+ responsive generation), Toast, Storage (with TTL), Clipboard, Date, Number/Currency (15+ currencies), String, Validation, Debounce/Throttle, Web Vitals.</p>
            </div>

            {{-- Feature 8: Hooks --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">20+ Custom React Hooks</h3>
                <p class="text-sm text-surface-foreground/60">useAuth, useUser, useHasRole, useHasPermission, useFlash, useErrors, useRoute, useIsRoute, useCsrfToken, useAppConfig, and more — all barrel exported.</p>
            </div>

            {{-- Feature 9: Artisan Commands --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">7 Artisan Scaffolders</h3>
                <p class="text-sm text-surface-foreground/60">make:feature (+ --roles), make:feature:controller, :service, :request, :event, :exception, :observer. Supports role-based nested paths.</p>
            </div>

            {{-- Feature 10: Tailwind v4 --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.172-1.172a4 4 0 015.656 0l1.172 1.172a4 4 0 010 5.656l-1.172 1.172a4 4 0 01-5.656 0l-1.172-1.172a4 4 0 010-5.656z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Tailwind CSS v4 Design Tokens</h3>
                <p class="text-sm text-surface-foreground/60">Semantic @theme tokens (primary, secondary, surface, error, success) using OKLCH color space. White-label ready — change one line to rebrand.</p>
            </div>

            {{-- Feature 11: Auth System --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Complete Auth System</h3>
                <p class="text-sm text-surface-foreground/60">Login, Register, Forgot Password, Reset Password — full Blade views, form validation, service-layer logic, and queued password reset emails.</p>
            </div>

            {{-- Feature 12: Dev Script --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Single-Command Dev</h3>
                <p class="text-sm text-surface-foreground/60"><code>composer dev</code> launches server, queue listener, Pail log viewer, and Vite HMR simultaneously. <code>composer setup</code> handles full project initialization.</p>
            </div>

            {{-- Feature 13: Activity Logs --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Activity Logs System</h3>
                <p class="text-sm text-surface-foreground/60">Event-driven audit trails with automatic IP/User-Agent tracking. Includes adaptive UI badges for standard actions like 'create', 'update', and 'delete'.</p>
            </div>

            {{-- Feature 14: Professional Dashboards --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">Professional Dashboards</h3>
                <p class="text-sm text-surface-foreground/60">Beautiful React dashboard modules for both User and Admin. Powered by dedicated <code>DashboardServices</code>, they feature live metric visualizers, profile health bars, and chronological activity feeds.</p>
            </div>

            {{-- Feature 15: User Management Hub --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">User Management Hub</h3>
                <p class="text-sm text-surface-foreground/60">A comprehensive control center with live search/pagination. Add users via secure modals or edit privileges and active bans leveraging FormRequests mapping to <code>UserService</code>.</p>
            </div>

            {{-- Feature 16: System Health Monitor --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">System Health Monitor</h3>
                <p class="text-sm text-surface-foreground/60">Visually striking Live Metrics diagnostic interface. Automatically ping database PDO integrity, caching latency, server memory limits, and view your framework stacks in real-time.</p>
            </div>

            {{-- Feature 17: UI Cache Management --}}
            <div class="relative p-6 bg-surface rounded-2xl border border-surface-foreground/5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-primary text-white shadow-lg mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-foreground mb-2">UI Cache Management</h3>
                <p class="text-sm text-surface-foreground/60">GUI representations for all backend Artisan caching utilities. Seamlessly flush Views, Configs, or Route caches directly from the interface encapsulated securely via <code>CacheService</code>.</p>
            </div>
        </div>

        {{-- Stats --}}
        <div class="bg-primary/5 rounded-3xl border border-primary/20 p-12 mb-24">
            <h2 class="text-2xl font-extrabold text-foreground text-center mb-10">FeatureKit by the Numbers</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-4xl font-black text-primary">7</div>
                    <p class="text-sm text-surface-foreground/60 mt-1">Artisan Commands</p>
                </div>
                <div>
                    <div class="text-4xl font-black text-primary">11</div>
                    <p class="text-sm text-surface-foreground/60 mt-1">JS Utility Modules</p>
                </div>
                <div>
                    <div class="text-4xl font-black text-primary">20+</div>
                    <p class="text-sm text-surface-foreground/60 mt-1">React Hooks</p>
                </div>
                <div>
                    <div class="text-4xl font-black text-primary">7</div>
                    <p class="text-sm text-surface-foreground/60 mt-1">UI Components</p>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center">
            <h2 class="text-3xl font-extrabold text-foreground mb-4">Ready to Build?</h2>
            <p class="text-xl text-surface-foreground/60 mb-8">
                Get started with a single command and focus on building your product.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('auth.register.index') }}"
                   class="px-8 py-4 bg-primary text-white font-bold rounded-xl text-lg hover:bg-primary/90 transition-colors shadow-lg">
                    Get Started Free
                </a>
                <a href="{{ route('landing.docs') }}"
                   class="px-8 py-4 bg-primary/10 text-primary font-bold rounded-xl text-lg hover:bg-primary/20 transition-colors">
                    Read Documentation
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
