<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOID = 'void';

    public const PAYMENT_MODES = ['cash', 'upi', 'card', 'other'];

    public const PAYMENT_STATUSES = ['paid', 'unpaid'];

    public const DISCOUNT_TYPES = ['flat', 'percent'];

    protected $fillable = [
        'invoice_number', 'public_code', 'customer_id', 'user_id', 'staff_member_id', 'invoice_date',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_amount',
        'round_off', 'total', 'payment_mode', 'payment_status', 'notes', 'status', 'void_reason',
        'voided_at', 'voided_by', 'whatsapp_sent_at', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'voided_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeVoid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VOID);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
