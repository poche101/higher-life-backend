<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Higher Life Admin' }}</title>
    
    <!-- Tailwind CSS CDN for quick setup -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased">
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
