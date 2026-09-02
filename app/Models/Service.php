<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'service_category_id', 'group_name', 'name', 'description',
        'price', 'price_max', 'duration_minutes', 'sort_order', 'is_active',
    ];

    protected $appends = ['display_name'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_max' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->group_name ? "{$this->group_name} – {$this->name}" : $this->name);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** True once the service appears on any invoice line (table exists from phase 2). */
    public function isBilled(): bool
    {
        return false;
    }
}
