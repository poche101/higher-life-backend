<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Higher Life Admin</title>
    {{-- Swap the CDN for a Vite-built Tailwind setup before going to production. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-[#F7F5FB] text-slate-800" x-data="{ open: false }">
@php
    $nav = [
        ['admin.dashboard', 'Dashboard'], ['admin.affirmations', 'Affirmations'], ['admin.categories', 'Categories'],
        ['admin.collections', 'Collections'], ['admin.users', 'Users'], ['admin.billing', 'Subscriptions & Payments'],
        ['admin.notifications', 'Notifications'],
    ];
@endphp
<div class="flex min-h-screen">
    <aside class="hidden md:flex w-64 flex-col bg-[#0B2A5B] text-white p-5 gap-1">
        <div class="mb-6"><div class="text-xs tracking-[.3em] text-[#D9B45F] font-bold">HIGHER LIFE</div><div class="text-lg font-serif">Admin</div></div>
        @foreach ($nav as [$route, $label])
            <a href="{{ route($route) }}" wire:navigate
               class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs($route) ? 'bg-white/15 font-semibold' : 'hover:bg-white/10 text-white/80' }}">{{ $label }}</a>
        @endforeach
        <form method="POST" action="{{ route('admin.logout') }}" class="mt-auto">@csrf
            <button class="w-full text-left rounded-lg px-3 py-2 text-sm text-white/70 hover:bg-white/10">Sign out</button>
        </form>
    </aside>
    <main class="flex-1 p-4 md:p-8 overflow-x-auto">
        <nav class="md:hidden flex flex-wrap gap-2 mb-4">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" class="text-xs rounded-full px-3 py-1 {{ request()->routeIs($route) ? 'bg-[#0B2A5B] text-white' : 'bg-white border' }}">{{ $label }}</a>
            @endforeach
        </nav>
        {{ $slot }}
    </main>
</div>
@livewireScripts
</body>
</html>
