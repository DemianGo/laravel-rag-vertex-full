<?php

namespace App\Services;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\PlanConfig;
use App\Models\AiProviderConfig;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingService
{
    private AiCostCalculator $costCalculator;
    private MercadoPagoService $mercadoPago;

    public function __construct(AiCostCalculator $costCalculator, MercadoPagoService $mercadoPago)
    {
        $this->costCalculator = $costCalculator;
        $this->mercadoPago = $mercadoPago;
    }

    /**
     * Calcula o custo de uma operação de IA
     */
    public function calculateAiCost(string $provider, string $model, int $inputTokens, int $outputTokens): array
    {
        try {
            $result = $this->costCalculator->calculateTotalCost($provider, $model, $inputTokens, $outputTokens);

            return [
                'success' => true,
                'cost' => $result['adjusted_cost'],
                'price' => $result['final_price'],
                'profit' => $result['markup_amount'],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'markup_percentage' => $result['markup_percentage'],
                'base_cost' => $result['base_cost']
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao calcular custo de IA', [
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao calcular custo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cobra tokens de um usuário
     */
    public function chargeUserTokens(User $user, int $tokens, string $operation = 'ai_usage'): array
    {
        try {
            DB::beginTransaction();

            // Verificar se o usuário tem tokens suficientes
            if ($user->tokens_used + $tokens > $user->tokens_limit) {
                return [
                    'success' => false,
                    'error' => 'Tokens insuficientes. Limite: ' . $user->tokens_limit . ', Usado: ' . $user->tokens_used . ', Necessário: ' . $tokens
                ];
            }

            // Atualizar tokens do usuário
            $user->increment('tokens_used', $tokens);

            // Registrar a cobrança
            $this->logTokenUsage($user, $tokens, $operation);

            DB::commit();

            return [
                'success' => true,
                'tokens_charged' => $tokens,
                'tokens_remaining' => $user->tokens_limit - $user->tokens_used,
                'tokens_used' => $user->tokens_used,
                'tokens_limit' => $user->tokens_limit
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cobrar tokens do usuário', [
                'user_id' => $user->id,
                'tokens' => $tokens,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao processar cobrança: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa um pagamento
     */
    public function processPayment(User $user, string $planName, string $paymentMethod = 'mercadopago'): array
    {
        try {
            $plan = PlanConfig::where('plan_name', $planName)->where('is_active', true)->first();
            
            if (!$plan) {
                return [
                    'success' => false,
                    'error' => 'Plano não encontrado ou inativo'
                ];
            }

            // Criar preferência de pagamento
            $preference = $this->mercadoPago->createPreference([
                'title' => $plan->display_name,
                'amount' => $plan->price_monthly,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'external_reference' => 'plan_' . $plan->id . '_user_' . $user->id,
                'notification_url' => url('/billing/webhook'),
                'success_url' => url('/billing/success'),
                'failure_url' => url('/billing/failure'),
                'pending_url' => url('/billing/pending')
            ]);

            if (!$preference['success']) {
                return [
                    'success' => false,
                    'error' => 'Erro ao criar preferência de pagamento: ' . $preference['error']
                ];
            }

            // Criar registro de pagamento
            $payment = Payment::create([
                'user_id' => $user->id,
                'external_id' => $preference['preference_id'],
                'status' => 'pending',
                'amount' => $plan->price_monthly,
                'currency' => 'BRL',
                'payment_method' => $paymentMethod,
                'gateway' => 'mercadopago',
                'gateway_data' => json_encode($preference),
                'metadata' => json_encode([
                    'plan_name' => $planName,
                    'plan_id' => $plan->id,
                    'operation' => 'subscription'
                ])
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'preference_id' => $preference['preference_id'],
                'checkout_url' => $preference['init_point'],
                'amount' => $plan->price_monthly,
                'plan' => $plan->display_name
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento', [
                'user_id' => $user->id,
                'plan_name' => $planName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao processar pagamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aprova um pagamento e ativa a assinatura
     */
    public function approvePayment(int $paymentId): array
    {
        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($paymentId);
            
            if ($payment->status !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'Pagamento já processado'
                ];
            }

            // Aprovar pagamento
            $payment->update([
                'status' => 'approved',
                'paid_at' => now()
            ]);

            // Obter dados do plano
            $metadata = json_decode($payment->metadata, true);
            $plan = PlanConfig::find($metadata['plan_id']);

            if (!$plan) {
                throw new \Exception('Plano não encontrado');
            }

            // Cancelar assinatura anterior se existir
            $user = $payment->user;
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            // Criar nova assinatura
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_config_id' => $plan->id,
                'status' => 'active',
                'amount' => $plan->price_monthly,
                'currency' => 'BRL',
                'billing_cycle' => 'monthly',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'auto_renew' => true,
                'metadata' => json_encode([
                    'payment_id' => $payment->id,
                    'upgraded_from' => $user->plan
                ])
            ]);

            // Atualizar usuário
            $user->update([
                'plan' => $plan->plan_name,
                'tokens_limit' => $plan->tokens_limit,
                'documents_limit' => $plan->documents_limit,
                'tokens_used' => 0, // Reset tokens ao fazer upgrade
                'documents_used' => 0 // Reset documentos ao fazer upgrade
            ]);

            DB::commit();

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'plan' => $plan->display_name,
                'tokens_limit' => $plan->tokens_limit,
                'documents_limit' => $plan->documents_limit,
                'expires_at' => $subscription->ends_at->format('d/m/Y')
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao aprovar pagamento', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao aprovar pagamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa webhook do Mercado Pago
     */
    public function processWebhook(array $data): array
    {
        try {
            $paymentId = $data['data']['id'] ?? null;
            
            if (!$paymentId) {
                return [
                    'success' => false,
                    'error' => 'ID do pagamento não encontrado'
                ];
            }

            // Buscar pagamento no banco
            $payment = Payment::where('external_id', $paymentId)->first();
            
            if (!$payment) {
                return [
                    'success' => false,
                    'error' => 'Pagamento não encontrado no banco'
                ];
            }

            // Verificar status do pagamento no Mercado Pago
            $paymentInfo = $this->mercadoPago->getPayment($paymentId);
            
            if (!$paymentInfo['success']) {
                return [
                    'success' => false,
                    'error' => 'Erro ao consultar pagamento: ' . $paymentInfo['error']
                ];
            }

            $status = $paymentInfo['status'];

            switch ($status) {
                case 'approved':
                    return $this->approvePayment($payment->id);
                    
                case 'rejected':
                    $payment->update(['status' => 'rejected']);
                    return [
                        'success' => true,
                        'message' => 'Pagamento rejeitado'
                    ];
                    
                case 'pending':
                    return [
                        'success' => true,
                        'message' => 'Pagamento pendente'
                    ];
                    
                default:
                    return [
                        'success' => false,
                        'error' => 'Status de pagamento desconhecido: ' . $status
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao processar webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registra uso de tokens
     */
    private function logTokenUsage(User $user, int $tokens, string $operation): void
    {
        // Aqui você pode implementar um sistema de logs mais detalhado
        Log::info('Uso de tokens registrado', [
            'user_id' => $user->id,
            'tokens' => $tokens,
            'operation' => $operation,
            'timestamp' => now()
        ]);
    }

    /**
     * Obtém estatísticas de cobrança
     */
    public function getBillingStats(): array
    {
        try {
            $totalRevenue = Payment::where('status', 'approved')->sum('amount');
            $monthlyRevenue = Payment::where('status', 'approved')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount');
            
            $activeSubscriptions = Subscription::where('status', 'active')->count();
            $pendingPayments = Payment::where('status', 'pending')->count();
            
            $topPlans = Subscription::where('status', 'active')
                ->with('planConfig')
                ->get()
                ->groupBy('planConfig.plan_name')
                ->map(function ($subscriptions) {
                    return $subscriptions->count();
                })
                ->sortDesc()
                ->take(3);

            return [
                'success' => true,
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
                'active_subscriptions' => $activeSubscriptions,
                'pending_payments' => $pendingPayments,
                'top_plans' => $topPlans
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao obter estatísticas de cobrança', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela uma assinatura
     */
    public function cancelSubscription(int $subscriptionId): array
    {
        try {
            $subscription = Subscription::findOrFail($subscriptionId);
            
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'auto_renew' => false
            ]);

            // Rebaixar usuário para plano free
            $user = $subscription->user;
            $user->update([
                'plan' => 'free',
                'tokens_limit' => 100,
                'documents_limit' => 1
            ]);

            return [
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso'
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao cancelar assinatura', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao cancelar assinatura: ' . $e->getMessage()
            ];
        }
    }
}
