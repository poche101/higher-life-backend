<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\Payments\PaymentService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Billing extends Component
{
    use WithPagination;

    public string $tab = 'review';

    public function updatingTab() { $this->resetPage(); }

    protected function admin()
    {
        $user = auth()->user();
        abort_unless($user?->isAdmin(), 403, 'Administrators only.');

        return $user;
    }

    public function approve(int $id): void
    {
        $admin = $this->admin();
        app(PaymentService::class)->approve(Transaction::findOrFail($id), $admin);
        session()->flash('status', 'Payment approved. Subscription activated.');
    }

    public function reject(int $id): void
    {
        $admin = $this->admin();
        app(PaymentService::class)->reject(Transaction::findOrFail($id), $admin, 'Rejected by admin');
        session()->flash('status', 'Payment rejected.');
    }

    public function render()
    {
        $this->admin();
        $rows = match ($this->tab) {
            'subscriptions' => Subscription::with('user')->latest()->paginate(15),
            'transactions' => Transaction::with('user')->latest()->paginate(15),
            default => Transaction::with('user')->where('status', 'review')->oldest()->paginate(15),
        };

        return view('livewire.admin.billing', ['rows' => $rows, 'reviewCount' => Transaction::where('status', 'review')->count()]);
    }
}
