<?php

namespace App\Services;

use App\Models\AiProviderConfig;
use App\Models\SystemConfig;
use App\Models\User;

class AiCostCalculator
{
    /**
     * Calcular custo total para uma operação de IA
     */
    public function calculateTotalCost($providerName, $modelName, $inputTokens, $outputTokens = 0, $user = null)
    {
        $provider = AiProviderConfig::active()
            ->provider($providerName)
            ->where('model_name', $modelName)
            ->first();

        if (!$provider) {
            throw new \Exception("Provedor de IA não encontrado: {$providerName}/{$modelName}");
        }

        // Calcular custo base
        $baseCost = $provider->calculateCost($inputTokens, $outputTokens);
        
        // Aplicar multiplicador global
        $multiplier = SystemConfig::get('ai_cost_multiplier', 1.0);
        $adjustedCost = $baseCost * $multiplier;

        // Calcular margem baseada no usuário/plano
        $markup = $this->getMarkupForUser($provider, $user);
        
        // Calcular preço final
        $finalPrice = $adjustedCost * (1 + ($markup / 100));

        return [
            'provider' => $provider,
            'base_cost' => $baseCost,
            'adjusted_cost' => $adjustedCost,
            'markup_percentage' => $markup,
            'markup_amount' => $adjustedCost * ($markup / 100),
            'final_price' => $finalPrice,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_per_input_token' => $inputTokens > 0 ? $baseCost / $inputTokens : 0,
            'cost_per_output_token' => $outputTokens > 0 ? $baseCost / $outputTokens : 0,
        ];
    }

    /**
     * Obter margem para um usuário específico
     */
    private function getMarkupForUser(AiProviderConfig $provider, $user = null)
    {
        if (!$user) {
            return $provider->base_markup_percentage;
        }

        // Se o usuário tem um plano ativo, usar a margem do plano
        $activeSubscription = $user->activeSubscription();
        if ($activeSubscription && $activeSubscription->planConfig) {
            return $activeSubscription->planConfig->margin_percentage;
        }

        // Se não tem plano, usar margem padrão do provedor
        return $provider->base_markup_percentage;
    }

    /**
     * Calcular custo estimado antes da operação
     */
    public function estimateCost($providerName, $modelName, $estimatedInputTokens, $estimatedOutputTokens = 0, $user = null)
    {
        return $this->calculateTotalCost(
            $providerName,
            $modelName,
            $estimatedInputTokens,
            $estimatedOutputTokens,
            $user
        );
    }

    /**
     * Verificar se o usuário tem tokens suficientes
     */
    public function checkUserTokens(User $user, $requiredTokens)
    {
        $activeSubscription = $user->activeSubscription();
        
        if (!$activeSubscription || !$activeSubscription->planConfig) {
            // Usuário sem plano ativo - usar limite do plano gratuito
            $freePlan = \App\Models\PlanConfig::where('plan_name', 'free')->first();
            $limit = $freePlan ? $freePlan->tokens_limit : 100;
        } else {
            $limit = $activeSubscription->planConfig->tokens_limit;
        }

        // Se limite é -1, é ilimitado
        if ($limit === -1) {
            return true;
        }

        return $user->tokens_used + $requiredTokens <= $limit;
    }

    /**
     * Debitar tokens do usuário
     */
    public function debitUserTokens(User $user, $tokens)
    {
        $user->increment('tokens_used', $tokens);
        $user->save();
    }

    /**
     * Obter estatísticas de custos por provedor
     */
    public function getProviderStats($providerName = null)
    {
        $query = AiProviderConfig::active();
        
        if ($providerName) {
            $query->provider($providerName);
        }

        $providers = $query->get();

        $stats = [];
        foreach ($providers as $provider) {
            $stats[] = [
                'provider' => $provider->provider_name,
                'model' => $provider->model_name,
                'display_name' => $provider->display_name,
                'input_cost_per_1k' => $provider->input_cost_per_1k,
                'output_cost_per_1k' => $provider->output_cost_per_1k,
                'base_markup' => $provider->base_markup_percentage,
                'is_default' => $provider->is_default,
                'context_length' => $provider->context_length
            ];
        }

        return $stats;
    }

    /**
     * Calcular ROI (Return on Investment) para um provedor
     */
    public function calculateROI($providerName, $modelName, $inputTokens, $outputTokens = 0, $user = null)
    {
        $cost = $this->calculateTotalCost($providerName, $modelName, $inputTokens, $outputTokens, $user);
        
        $baseCost = $cost['adjusted_cost'];
        $finalPrice = $cost['final_price'];
        
        if ($baseCost <= 0) {
            return 0;
        }

        $profit = $finalPrice - $baseCost;
        $roi = ($profit / $baseCost) * 100;

        return [
            'base_cost' => $baseCost,
            'final_price' => $finalPrice,
            'profit' => $profit,
            'roi_percentage' => $roi,
            'markup_percentage' => $cost['markup_percentage']
        ];
    }
}
