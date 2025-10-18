<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlanConfig extends Model
{
    protected $fillable = [
        'plan_name',
        'display_name',
        'price_monthly',
        'price_yearly',
        'tokens_limit',
        'documents_limit',
        'features',
        'margin_percentage',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope para planos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para ordenação
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }

    /**
     * Busca planos configuráveis (cache)
     */
    public static function getActivePlans()
    {
        return Cache::remember('plan_configs_active', 3600, function () {
            return static::active()->ordered()->get();
        });
    }

    /**
     * Limpa cache quando modelo é atualizado
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('plan_configs_active');
        });

        static::deleted(function () {
            Cache::forget('plan_configs_active');
        });
    }

    /**
     * Calcula preço com desconto anual
     */
    public function getYearlyDiscountAttribute(): float
    {
        if ($this->price_monthly == 0) {
            return 0;
        }

        $yearlyEquivalent = $this->price_monthly * 12;
        return (($yearlyEquivalent - $this->price_yearly) / $yearlyEquivalent) * 100;
    }

    /**
     * Verifica se é plano gratuito
     */
    public function isFree(): bool
    {
        return $this->plan_name === 'free' || $this->price_monthly == 0;
    }

    /**
     * Retorna configuração do plano em formato legível
     */
    public function getConfig(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'price' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'tokens_limit' => $this->tokens_limit,
            'documents_limit' => $this->documents_limit,
            'features' => $this->features ?? [],
            'margin_percentage' => $this->margin_percentage,
            'is_free' => $this->isFree(),
            'yearly_discount' => $this->yearly_discount,
        ];
    }
}
