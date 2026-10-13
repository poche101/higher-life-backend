<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Higher Life Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-[#0B2A5B] flex items-center justify-center p-4">
    {{ $slot }}
    @livewireScripts
</body>
</html>
