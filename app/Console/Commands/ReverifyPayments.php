<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

/** Safety net: re-asks Espees about recent unpaid payments that already have an Espees payment_ref, so a user who closed the app mid-payment still gets activated. */
class ReverifyPayments extends Command
{
    protected $signature = 'payments:reverify';
    protected $description = 'Re-check recent pending Espees payments with the Confirm Payment API';

    public function handle(PaymentService $payments): int
    {
        if (config('espee.driver') !== 'espee') return self::SUCCESS;

        $checked = 0;
        Transaction::where('gateway', 'espee')->where('status', 'pending')->whereNotNull('gateway_ref')->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')->limit(50)->get()
            ->each(function (Transaction $tx) use ($payments, &$checked) {
                rescue(fn () => $payments->verify($tx->reference), report: true);
                $checked++;
            });

        $this->info("Re-checked {$checked} payment(s).");
        return self::SUCCESS;
    }
}
