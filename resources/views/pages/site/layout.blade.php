{{-- Public site layout (Landing). Plain Blade: CSS only, no React bundle. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description')">

    {{-- Built from APP_URL, so the canonical address never follows a request's Host header. --}}
    <link rel="canonical" href="{{ rtrim(config('app.url'), '/').'/'.ltrim(request()->path(), '/') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ rtrim(config('app.url'), '/').'/'.ltrim(request()->path(), '/') }}">
    <meta property="og:image" content="{{ asset('logo.png') }}">
    <meta name="twitter:card" content="summary">

    @vite('resources/css/app.css')
</head>
<body class="min-h-screen flex flex-col bg-background text-foreground antialiased font-sans">
    @include('pages.site.partials.header')

    <main class="flex-grow">
        @yield('content')
    </main>

    @include('pages.site.partials.legal-footer')
</body>
</html>
