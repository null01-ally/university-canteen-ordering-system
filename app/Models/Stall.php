<?php

namespace App\Models;

use Database\Factories\StallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'phone', 'is_active', 'delivery_fee', 'pickup_enabled', 'delivery_enabled',
    'opens_at', 'closes_at', 'break_starts_at', 'break_ends_at',
    'location', 'description', 'preparation_minutes', 'weekly_hours', 'image_path',
])]
class Stall extends Model
{
    /** @use HasFactory<StallFactory> */
    use HasFactory;

    public const DEFAULT_ID = 1;

    protected $attributes = [
        'is_active' => true,
        'delivery_fee' => 0,
        'pickup_enabled' => true,
        'delivery_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'array',
            'is_active' => 'boolean',
            'delivery_fee' => 'decimal:2',
            'pickup_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
        ];
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'staff');
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return is_file(public_path('images/stalls/'.$this->id.'.jpg'))
            ? asset('images/stalls/'.$this->id.'.jpg')
            : null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function accepts(string $method): bool
    {
        return $this->is_active && $this->withinOperatingHours() && match ($method) {
            'pickup' => $this->pickup_enabled,
            'delivery' => $this->delivery_enabled,
            default => false,
        };
    }

    private function withinOperatingHours(): bool
    {
        $now = now(config('canteen.timezone'));
        $time = $now->format('H:i');
        if ($this->weekly_hours !== null) {
            $slot = $this->weekly_hours[$now->dayOfWeekIso] ?? null;
            if (! is_array($slot) || ! in_array($slot['enabled'] ?? false, [true, 1, '1'], true) || ! isset($slot['opens'], $slot['closes'])
                || ! is_string($slot['opens']) || ! is_string($slot['closes'])
                || ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $slot['opens']) || ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $slot['closes'])
                || $time < $slot['opens'] || $time >= $slot['closes']) {
                return false;
            }
        }
        $opens = substr((string) $this->opens_at, 0, 5);
        $closes = substr((string) $this->closes_at, 0, 5);
        $breakStarts = substr((string) $this->break_starts_at, 0, 5);
        $breakEnds = substr((string) $this->break_ends_at, 0, 5);
        if (($opens === '') !== ($closes === '') || ($breakStarts === '') !== ($breakEnds === '')) {
            return false;
        }

        return ($opens === '' || ($time >= $opens && $time < $closes))
            && ($breakStarts === '' || $time < $breakStarts || $time >= $breakEnds);
    }

    public function isTakingOrders(): bool
    {
        return $this->is_active && ($this->pickup_enabled || $this->delivery_enabled) && $this->withinOperatingHours();
    }
}
