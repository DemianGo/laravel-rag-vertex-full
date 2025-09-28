<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'tokens_used',
        'tokens_limit',
        'documents_used',
        'documents_limit',
        'last_reset',
        'plan_expires_at',
        'auto_renew',
    ];

    protected $casts = [
        'last_reset' => 'datetime',
        'plan_expires_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get plan configuration
     */
    public function getPlanConfig(): array
    {
        return match ($this->plan) {
            'free' => [
                'name' => 'Free Plan',
                'price' => 0,
                'tokens_limit' => 100,
                'documents_limit' => 1,
                'features' => [
                    'Basic RAG queries',
                    'PDF extraction',
                    'Community support'
                ]
            ],
            'pro' => [
                'name' => 'Pro Plan',
                'price' => 15,
                'tokens_limit' => 10000,
                'documents_limit' => 50,
                'features' => [
                    'Advanced RAG generation',
                    'All extraction methods',
                    'Priority processing',
                    'Email support',
                    'API access'
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise Plan',
                'price' => 30,
                'tokens_limit' => 999999,
                'documents_limit' => 999999,
                'features' => [
                    'Unlimited usage',
                    'Admin panel',
                    'Custom deployment',
                    'Priority support',
                    'Advanced analytics',
                    'Webhook integrations'
                ]
            ]
        };
    }

    /**
     * Check if user can perform action
     */
    public function canUseTokens(int $amount = 1): bool
    {
        $this->resetIfNeeded();
        return $this->tokens_used + $amount <= $this->tokens_limit;
    }

    /**
     * Check if user can add documents
     */
    public function canAddDocument(): bool
    {
        return $this->documents_used < $this->documents_limit;
    }

    /**
     * Use tokens
     */
    public function useTokens(int $amount = 1): void
    {
        if ($this->canUseTokens($amount)) {
            $this->increment('tokens_used', $amount);
        }
    }

    /**
     * Add document usage
     */
    public function useDocument(): void
    {
        if ($this->canAddDocument()) {
            $this->increment('documents_used');
        }
    }

    /**
     * Reset usage if needed (monthly)
     */
    public function resetIfNeeded(): void
    {
        if (!$this->last_reset || $this->last_reset->lt(Carbon::now()->startOfMonth())) {
            $this->update([
                'tokens_used' => 0,
                'documents_used' => 0,
                'last_reset' => Carbon::now()
            ]);
        }
    }

    /**
     * Check if plan is expired
     */
    public function isExpired(): bool
    {
        return $this->plan_expires_at && $this->plan_expires_at->lt(Carbon::now());
    }

    /**
     * Get usage percentage
     */
    public function getTokenUsagePercentage(): float
    {
        if ($this->tokens_limit <= 0) return 0;
        return ($this->tokens_used / $this->tokens_limit) * 100;
    }

    /**
     * Get document usage percentage
     */
    public function getDocumentUsagePercentage(): float
    {
        if ($this->documents_limit <= 0) return 0;
        return ($this->documents_used / $this->documents_limit) * 100;
    }
}