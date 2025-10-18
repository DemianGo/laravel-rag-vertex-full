<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_config_id',
        'status',
        'amount',
        'currency',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'auto_renew',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relacionamentos
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function planConfig()
    {
        return $this->belongsTo(PlanConfig::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now());
    }

    /**
     * Verifica se a assinatura está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->ends_at && 
               $this->ends_at->isFuture();
    }

    /**
     * Verifica se a assinatura está expirada
     */
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Verifica se a assinatura está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Ativa a assinatura
     */
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $this->billing_cycle === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth(),
        ]);
    }

    /**
     * Cancela a assinatura
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Renova a assinatura
     */
    public function renew(): void
    {
        if ($this->isActive() && $this->auto_renew) {
            $this->update([
                'ends_at' => $this->billing_cycle === 'yearly' 
                    ? $this->ends_at->addYear() 
                    : $this->ends_at->addMonth(),
            ]);
        }
    }

    /**
     * Calcula dias restantes
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }
}
