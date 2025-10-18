<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Payment extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'external_id',
        'status',
        'amount',
        'currency',
        'payment_method',
        'gateway',
        'gateway_data',
        'paid_at',
        'expires_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_data' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relacionamentos
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('created_at', now()->year);
    }

    /**
     * Verifica se o pagamento foi aprovado
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Verifica se o pagamento está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se o pagamento foi rejeitado
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Verifica se o pagamento expirou
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Aprova o pagamento
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'paid_at' => now(),
        ]);
    }

    /**
     * Rejeita o pagamento
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Formata valor para exibição
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    /**
     * Retorna status em português
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'approved' => 'Aprovado',
            'pending' => 'Pendente',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado',
            default => 'Desconhecido',
        };
    }
}
