<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans()
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)->get()->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'currency' => $p->currency,
            'billing_interval' => $p->billing_interval, 'duration_days' => $p->duration_days,
        ])]);
    }

    protected function summary(Request $request): array
    {
        $sub = $request->user()->activeSubscription();

        return [
            'is_premium' => $sub !== null,
            'has_access' => $request->user()->hasAccess(),
            'on_trial' => $request->user()->onTrial(),
            'trial_ends_at' => $request->user()->trial_ends_at?->toIso8601String(),
            'trial_days_left' => $request->user()->trialDaysLeft(),
            'status' => $sub?->status ?? 'none',
            'plan' => $sub?->plan?->name,
            'starts_at' => $sub?->starts_at?->toIso8601String(),
            'expires_at' => $sub?->expires_at?->toIso8601String(),
            'days_left' => $sub ? (int) now()->diffInDays($sub->expires_at, false) : 0,
            'auto_renew' => $sub?->auto_renew ?? false,
            'cancelled_at' => $sub?->cancelled_at?->toIso8601String(),
        ];
    }

    public function show(Request $request) { return response()->json($this->summary($request)); }
    public function status(Request $request) { return response()->json($this->summary($request)); }

    public function subscribe(Request $request, PaymentService $payments)
    {
        $data = $request->validate(['plan_id' => 'nullable|integer|exists:subscription_plans,id']);
        $plan = isset($data['plan_id'])
            ? SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id'])
            : SubscriptionPlan::where('is_active', true)->firstOrFail();

        try {
            $r = $payments->initiate($request->user(), $plan);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Payments are temporarily unavailable. Please try again shortly.'], 503);
        }

        return response()->json($payments->payload($r['transaction'], $r['checkout_url']), 201);
    }

    public function cancel(Request $request)
    {
        $sub = $request->user()->activeSubscription();
        abort_unless($sub, 422, 'You have no active subscription.');

        $sub->update(['auto_renew' => false, 'cancelled_at' => now()]);

        return response()->json(['message' => 'Auto-renewal cancelled. Premium stays active until your expiry date.'] + $this->summary($request));
    }
}
