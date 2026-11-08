<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'user_type' => 'customer',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'customer_id');
    }

    public function stall(): BelongsTo
    {
        return $this->belongsTo(Stall::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function pickupNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'customer_id');
    }

    /** @return Collection<int, Notification> */
    public function unreadPickupNotifications(): Collection
    {
        $notifications = $this->pickupNotifications()->where('is_read', false)
            ->where(fn ($query) => $query->whereNull('order_id')->orWhereHas('order', fn ($orders) => $orders->where('status', 'ready')))
            ->latest()->latest('id')->limit(20)->get();
        $references = $notifications->map(fn (Notification $notification): ?string => $notification->order_id === null ? $notification->readyOrderReference() : null)->filter();
        $ready = $references->isEmpty()
            ? []
            : $this->orders()->where('status', 'ready')->whereIn('reference_number', $references->all())
                ->pluck('reference_number')->map(fn (string $reference): string => strtolower($reference))->flip()->all();

        return $notifications->filter(function (Notification $notification) use ($ready): bool {
            if ($notification->order_id !== null) {
                return true;
            }
            $reference = $notification->readyOrderReference();

            return $reference === null || isset($ready[$reference]);
        });
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(SalesReport::class, 'admin_id');
    }

    public function revokeDatabaseSessions(): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))
                ->where('user_id', $this->id)->delete();
        }
    }

    public function revokePasswordResetTokens(string ...$emails): void
    {
        $repository = Password::getRepository();
        foreach (array_unique([$this->email, ...$emails]) as $email) {
            $repository->delete(new self(['email' => $email]));
        }
    }
}
