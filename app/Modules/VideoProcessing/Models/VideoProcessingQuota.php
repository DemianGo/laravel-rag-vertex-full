<?php

namespace App\Modules\VideoProcessing\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VideoProcessingQuota extends Model
{
    protected $fillable = [
        'tenant_slug',
        'daily_limit',
        'monthly_limit',
        'max_duration_seconds',
        'used_today',
        'used_this_month',
        'last_reset_date',
    ];

    protected $casts = [
        'last_reset_date' => 'date',
    ];

    /**
     * Check if tenant can process a video
     */
    public function canProcess(int $duration): bool
    {
        // Check daily limit
        if ($this->used_today >= $this->daily_limit) {
            return false;
        }
        
        // Check monthly limit
        if ($this->used_this_month >= $this->monthly_limit) {
            return false;
        }
        
        // Check duration limit
        if ($duration > $this->max_duration_seconds) {
            return false;
        }
        
        return true;
    }

    /**
     * Increment usage counters
     */
    public function incrementUsage(): void
    {
        $this->increment('used_today');
        $this->increment('used_this_month');
    }

    /**
     * Reset daily usage
     */
    public function resetDaily(): void
    {
        if ($this->last_reset_date->isToday()) {
            return; // Already reset today
        }
        
        $this->update([
            'used_today' => 0,
            'last_reset_date' => today(),
        ]);
    }

    /**
     * Reset monthly usage
     */
    public function resetMonthly(): void
    {
        $this->update(['used_this_month' => 0]);
    }

    /**
     * Get remaining daily quota
     */
    public function getRemainingDaily(): int
    {
        return max(0, $this->daily_limit - $this->used_today);
    }

    /**
     * Get remaining monthly quota
     */
    public function getRemainingMonthly(): int
    {
        return max(0, $this->monthly_limit - $this->used_this_month);
    }

    /**
     * Boot method to auto-reset daily quotas
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            // Auto-reset daily quota if it's a new day
            if (!$model->last_reset_date || !$model->last_reset_date->isToday()) {
                $model->used_today = 0;
                $model->last_reset_date = today();
            }
        });
    }
}

