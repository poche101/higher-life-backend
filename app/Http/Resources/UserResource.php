<?php

namespace App\Http\Resources;

use App\Services\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sub = $this->activeSubscription();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => str_ends_with((string) $this->email, '.invalid') ? null : $this->email,
            'avatar' => MediaUrl::thumb($this->avatar),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'email_verified' => $this->email_verified_at !== null,
            'is_premium' => $sub !== null,
            'subscription_expires_at' => $sub?->expires_at?->toIso8601String(),
            'has_access' => $this->hasAccess(),
            'on_trial' => $this->onTrial(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'trial_days_left' => $this->trialDaysLeft(),
            'notifications_enabled' => $this->notifications_enabled,
            'notification_time' => substr((string) $this->notification_time, 0, 5),
            'reminder_frequency' => $this->reminder_frequency,
        ];
    }
}
