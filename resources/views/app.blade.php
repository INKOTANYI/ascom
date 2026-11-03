<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ \App\Models\SchoolProfile::current()?->name ?? config('app.name') }}</title>
        <link rel="icon" href="{{ \App\Models\SchoolProfile::current()?->logo_path ? route('school.logo') : '/favicon.ico' }}">
        <script>
            // Dark mode before the first paint, so pages never flash white.
            try {
                const saved = localStorage.getItem('theme');
                if (saved === 'dark' || (!saved && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
            } catch (e) {}
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
        @inertia
    </body>
</html>
