<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'password', 'date_of_birth', 'avatar', 'role',
        'notifications_enabled', 'notification_time', 'reminder_frequency', 'kingschat_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function deviceTokens(): HasMany { return $this->hasMany(DeviceToken::class); }
    public function progress(): HasMany { return $this->hasMany(UserProgress::class); }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Affirmation::class, 'favorites')->withTimestamps();
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    public function isPremium(): bool { return $this->activeSubscription() !== null; }
}
