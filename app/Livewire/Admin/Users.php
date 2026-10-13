<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function render()
    {
        $users = User::withExists(['subscriptions as subscriptions_exists' => fn ($q) => $q->where('status', 'active')->where('expires_at', '>', now())])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))
            ->latest()->paginate(15);

        return view('livewire.admin.users', ['users' => $users]);
    }
}
