<?php

namespace App\Services;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\PlanConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestBillingService
{
    /**
     * Processa um pagamento de teste (simula aprovação imediata)
     */
    public function processTestPayment(User $user, string $planName): array
    {
        try {
            $plan = PlanConfig::where('plan_name', $planName)->where('is_active', true)->first();
            
            if (!$plan) {
                return [
                    'success' => false,
                    'error' => 'Plano não encontrado ou inativo'
                ];
            }

            DB::beginTransaction();

            // Cancelar assinatura anterior se existir
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            // Criar nova assinatura primeiro
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
                    'upgraded_from' => $user->plan,
                    'test_mode' => true
                ])
            ]);

            // Criar registro de pagamento
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'external_id' => 'test_payment_' . time(),
                'status' => 'approved',
                'amount' => $plan->price_monthly,
                'currency' => 'BRL',
                'payment_method' => 'test',
                'gateway' => 'test',
                'gateway_data' => json_encode(['test' => true]),
                'paid_at' => now(),
                'metadata' => json_encode([
                    'plan_name' => $planName,
                    'plan_id' => $plan->id,
                    'operation' => 'subscription',
                    'test_mode' => true
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
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'plan' => $plan->display_name,
                'tokens_limit' => $plan->tokens_limit,
                'documents_limit' => $plan->documents_limit,
                'expires_at' => $subscription->ends_at->format('d/m/Y'),
                'test_mode' => true
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao processar pagamento de teste', [
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
     * Simula um webhook de pagamento aprovado
     */
    public function simulateWebhookApproval(int $paymentId): array
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            if ($payment->status !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'Pagamento já processado'
                ];
            }

            DB::beginTransaction();

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
                'tokens_used' => 0,
                'documents_used' => 0
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
            Log::error('Erro ao simular webhook', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao simular webhook: ' . $e->getMessage()
            ];
        }
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
}
