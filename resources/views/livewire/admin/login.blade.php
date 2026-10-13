<form wire:submit="login" class="w-full max-w-sm bg-white rounded-2xl shadow-xl p-8 space-y-4">
    <div class="text-center">
        <div class="text-xs tracking-[.3em] text-[#B98B2E] font-bold">HIGHER LIFE</div>
        <h1 class="text-2xl font-serif text-[#0B2A5B] mt-1">Welcome back</h1>
    </div>
    <div>
        <input wire:model="email" type="email" placeholder="Email" class="w-full rounded-lg border px-3 py-2">
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <input wire:model="password" type="password" placeholder="Password" class="w-full rounded-lg border px-3 py-2">
    <button class="w-full rounded-lg bg-[#0B2A5B] text-white py-2.5 font-semibold hover:opacity-90">Sign in</button>
</form>
