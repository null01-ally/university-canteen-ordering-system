<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['stall_id', 'category_id', 'name', 'description', 'image_path', 'price', 'is_available', 'stock_qty'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'stall_id' => Stall::DEFAULT_ID,
        'is_available' => true,
        'stock_qty' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'stock_qty' => 'integer',
        ];
    }

    public function canOrder(int $quantity = 1): bool
    {
        return $quantity > 0 && $this->is_available && $this->stock_qty >= $quantity;
    }

    public function imageUrl(): string
    {
        $path = trim((string) $this->image_path);
        if (filter_var($path, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $path;
        }
        if ($path === '' || str_contains($path, ':') || str_contains($path, '..') || str_starts_with($path, '//')) {
            return asset('images/icons/menu-placeholder.png');
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stall(): BelongsTo
    {
        return $this->belongsTo(Stall::class);
    }
}
