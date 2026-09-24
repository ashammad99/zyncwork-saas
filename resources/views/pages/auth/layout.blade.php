<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <meta name="description" content="@yield('description', 'Default description')">
    <meta name="keywords" content="@yield('keywords', 'courses, learning')">

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('og_title', config('app.name'))">
    <meta property="og:description" content="@yield('og_description', 'Default description')">
    <meta property="og:image" content="@yield('og_image', asset('default.jpg'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('app.name'))">
    <meta name="twitter:description" content="@yield('og_description', 'Default description')">
    <meta name="twitter:image" content="@yield('og_image', asset('default.jpg'))">

    @vite('resources/css/app.css')
</head>
<body class="bg-background text-foreground antialiased font-sans">
    <div class="min-h-screen flex flex-col lg:flex-row overflow-hidden">

    {{-- LEFT SIDE (Brand - Desktop only) --}}
    <div class="hidden lg:flex flex-1 relative overflow-hidden bg-secondary-surface border-r border-border items-center justify-center p-12">

        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
             style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 24px 24px;">
        </div>

        <div class="relative z-10 text-center max-w-sm">

            {{-- Logo --}}
            <div class="mx-auto mb-10 w-40 h-40 relative group">
                <img src="/logo.png"
                     alt="Logo"
                     class="w-full h-full object-contain drop-shadow-2xl transition-transform duration-700 group-hover:scale-110"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                {{-- Fallback --}}
                <div class="hidden absolute inset-0 bg-primary rounded-[2.5rem] items-center justify-center text-primary-foreground font-bold text-6xl shadow-2xl">
                    {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                </div>
            </div>

            {{-- App Name --}}
            <h2 class="text-4xl font-extrabold text-primary mb-6 tracking-tight">
                @yield('app_name', config('app.name'))
            </h2>

            {{-- Description --}}
            <p class="text-secondary-surface-foreground text-xl font-medium leading-relaxed">
                @yield('description', 'Welcome back! Please enter your details to sign in to your account.')
            </p>
        </div>
    </div>

    {{-- RIGHT SIDE (Form Area) --}}
    <div class="flex-1 flex flex-col justify-start lg:justify-center px-6 py-12 lg:px-20 xl:px-32 relative z-10 bg-surface min-h-screen lg:min-h-0 overflow-y-auto">

        {{-- Form Container --}}
        <div class="mx-auto w-full max-w-2xl">
            @yield('content')
        </div>
    </div>
</div>

@include('pages.components.ui.loading-spinner')

<script>
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.tagName === 'FORM') {
            const spinner = document.getElementById('loading-spinner');
            if (spinner) {
                spinner.classList.remove('hidden');
            }
        }
    });
</script>
</body>
</html>