<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Append-only audit trail shown to the owner under Settings → Activity.
 * Rows are never edited; App\Console\Commands\PruneActivityLog trims old ones.
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = [
        'user_id', 'user_name', 'action', 'subject_type', 'subject_id',
        'subject_label', 'description', 'changes', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record one action. Never throws: an audit failure must not break billing.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function log(string $action, string $description, ?Model $subject = null, ?array $changes = null, ?string $label = null): void
    {
        try {
            $user = Auth::user();

            static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $label,
                'description' => $description,
                'changes' => $changes ?: null,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Human label for the action, e.g. "invoice.voided" → "Invoice voided". */
    public function getActionLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? ucfirst(str_replace(['.', '_'], ' ', $this->action));
    }

    public const LABELS = [
        'invoice.created' => 'Invoice created',
        'invoice.edited' => 'Invoice edited',
        'invoice.voided' => 'Invoice voided',
        'invoice.sent' => 'Invoice sent on WhatsApp',
        'expense.created' => 'Expense added',
        'expense.updated' => 'Expense edited',
        'expense.deleted' => 'Expense deleted',
        'service.created' => 'Service added',
        'service.updated' => 'Service updated',
        'service.deleted' => 'Service deleted',
        'category.created' => 'Category added',
        'category.updated' => 'Category updated',
        'category.deleted' => 'Category deleted',
        'customer.updated' => 'Customer edited',
        'customer.created' => 'Customer added',
        'user.created' => 'User added',
        'user.updated' => 'User updated',
        'staff.created' => 'Staff member added',
        'staff.updated' => 'Staff member updated',
        'settings.updated' => 'Settings changed',
        'auth.login' => 'Signed in',
        'auth.login_failed' => 'Failed sign-in',
        'auth.logout' => 'Signed out',
    ];
}
