<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_name',
        'model_name',
        'display_name',
        'input_cost_per_1k',
        'output_cost_per_1k',
        'context_length',
        'base_markup_percentage',
        'min_markup_percentage',
        'max_markup_percentage',
        'is_active',
        'is_default',
        'sort_order',
        'metadata'
    ];

    protected $casts = [
        'input_cost_per_1k' => 'decimal:6',
        'output_cost_per_1k' => 'decimal:6',
        'base_markup_percentage' => 'decimal:2',
        'min_markup_percentage' => 'decimal:2',
        'max_markup_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Scope para buscar apenas provedores ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar por provedor
     */
    public function scopeProvider($query, $provider)
    {
        return $query->where('provider_name', $provider);
    }

    /**
     * Buscar o provedor padrão
     */
    public static function getDefault()
    {
        return static::active()->where('is_default', true)->first();
    }

    /**
     * Calcular custo total para tokens de entrada e saída
     */
    public function calculateCost($inputTokens, $outputTokens = 0)
    {
        $inputCost = ($inputTokens / 1000) * $this->input_cost_per_1k;
        $outputCost = ($outputTokens / 1000) * $this->output_cost_per_1k;
        
        return $inputCost + $outputCost;
    }

    /**
     * Calcular preço final com margem
     */
    public function calculatePrice($inputTokens, $outputTokens = 0, $customMarkup = null)
    {
        $baseCost = $this->calculateCost($inputTokens, $outputTokens);
        $markup = $customMarkup ?? $this->base_markup_percentage;
        
        // Garantir que a margem está dentro dos limites
        $markup = max($this->min_markup_percentage, min($this->max_markup_percentage, $markup));
        
        return $baseCost * (1 + ($markup / 100));
    }

    /**
     * Obter margem efetiva baseada no plano do usuário
     */
    public function getEffectiveMarkup($userPlan = null)
    {
        if (!$userPlan) {
            return $this->base_markup_percentage;
        }

        // Se o usuário tem um plano específico, usar a margem do plano
        if (method_exists($userPlan, 'margin_percentage')) {
            return $userPlan->margin_percentage;
        }

        return $this->base_markup_percentage;
    }
}