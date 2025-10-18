<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\PlanConfig;
use App\Models\Payment;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Cria checkout para upgrade de plano
     */
    public function createCheckout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plan_configs,id',
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        $user = Auth::user();
        $plan = PlanConfig::findOrFail($request->plan_id);

        // Verificar se o usuário já tem uma assinatura ativa
        $activeSubscription = $user->activeSubscription();
        if ($activeSubscription && $activeSubscription->planConfig->id === $plan->id) {
            return response()->json([
                'error' => 'Você já possui este plano ativo'
            ], 400);
        }

        // Criar assinatura pendente
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_config_id' => $plan->id,
            'status' => 'pending',
            'amount' => $request->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
            'currency' => 'BRL',
            'billing_cycle' => $request->billing_cycle,
            'metadata' => [
                'plan_name' => $plan->plan_name,
                'billing_cycle' => $request->billing_cycle,
                'created_via' => 'web_checkout'
            ]
        ]);

        // Criar preferência no Mercado Pago
        try {
            $preferenceData = [
                'title' => "Upgrade para {$plan->display_name}",
                'amount' => $subscription->amount,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'external_reference' => "sub_{$subscription->id}",
                'success_url' => route('payment.success'),
                'failure_url' => route('payment.failure'),
                'pending_url' => route('payment.pending'),
                'notification_url' => route('payment.webhook')
            ];

            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            // Criar registro de pagamento
            Payment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'external_id' => $preference['id'],
                'status' => 'pending',
                'amount' => $subscription->amount,
                'currency' => 'BRL',
                'gateway' => 'mercadopago',
                'gateway_data' => $preference,
                'expires_at' => now()->addHours(24), // Preferências expiram em 24h
                'metadata' => [
                    'preference_id' => $preference['id'],
                    'plan_name' => $plan->plan_name,
                    'billing_cycle' => $request->billing_cycle
                ]
            ]);

            Log::info('Checkout created successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan' => $plan->plan_name,
                'amount' => $subscription->amount,
                'preference_id' => $preference['id']
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $preference['init_point'],
                'preference_id' => $preference['id'],
                'subscription_id' => $subscription->id,
                'amount' => $subscription->amount,
                'plan' => $plan->display_name
            ]);

        } catch (Exception $e) {
            Log::error('Checkout creation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            // Marcar assinatura como falhou
            $subscription->update(['status' => 'failed']);

            return response()->json([
                'error' => 'Erro ao processar pagamento. Tente novamente.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Página de sucesso do pagamento
     */
    public function success(Request $request)
    {
        $preferenceId = $request->query('preference_id');
        $externalRef = $request->query('external_reference');

        if (!$preferenceId && !$externalRef) {
            return redirect()->route('plans.index')
                ->with('error', 'Parâmetros de pagamento inválidos');
        }

        // Buscar pagamento
        $payment = Payment::where('external_id', $preferenceId)
            ->orWhere('metadata->preference_id', $preferenceId)
            ->orWhere('metadata->external_reference', $externalRef)
            ->first();

        if (!$payment) {
            return redirect()->route('plans.index')
                ->with('error', 'Pagamento não encontrado');
        }

        // Atualizar status se necessário
        if ($payment->status === 'pending') {
            try {
                $mpPayment = $this->mercadoPagoService->getPayment($preferenceId);
                if ($mpPayment && $mpPayment['status'] === 'approved') {
                    $this->processApprovedPayment($payment);
                }
            } catch (Exception $e) {
                Log::error('Error checking payment status on success page', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return view('payment.success', compact('payment'));
    }

    /**
     * Página de falha do pagamento
     */
    public function failure(Request $request)
    {
        $preferenceId = $request->query('preference_id');
        $externalRef = $request->query('external_reference');

        $payment = null;
        if ($preferenceId || $externalRef) {
            $payment = Payment::where('external_id', $preferenceId)
                ->orWhere('metadata->preference_id', $preferenceId)
                ->orWhere('metadata->external_reference', $externalRef)
                ->first();
        }

        return view('payment.failure', compact('payment'));
    }

    /**
     * Página de pagamento pendente
     */
    public function pending(Request $request)
    {
        $preferenceId = $request->query('preference_id');
        $externalRef = $request->query('external_reference');

        $payment = null;
        if ($preferenceId || $externalRef) {
            $payment = Payment::where('external_id', $preferenceId)
                ->orWhere('metadata->preference_id', $preferenceId)
                ->orWhere('metadata->external_reference', $externalRef)
                ->first();
        }

        return view('payment.pending', compact('payment'));
    }

    /**
     * Processa pagamento aprovado
     */
    private function processApprovedPayment(Payment $payment): void
    {
        $payment->approve();

        // Ativar assinatura
        $subscription = $payment->subscription;
        $subscription->activate();

        // Atualizar usuário
        $user = $payment->user;
        $plan = $subscription->planConfig;
        
        $user->update([
            'plan' => $plan->plan_name,
            'tokens_limit' => $plan->tokens_limit,
            'documents_limit' => $plan->documents_limit,
            'tokens_used' => 0, // Reset tokens
            'documents_used' => 0, // Reset documents
        ]);

        Log::info('Payment approved and subscription activated', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'plan' => $plan->plan_name,
            'amount' => $payment->amount
        ]);
    }

    /**
     * Cancela assinatura
     */
    public function cancel(Subscription $subscription)
    {
        $user = Auth::user();

        // Verificar se a assinatura pertence ao usuário
        if ($subscription->user_id !== $user->id) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        // Verificar se pode cancelar
        if ($subscription->status !== 'active') {
            return response()->json(['error' => 'Assinatura não está ativa'], 400);
        }

        $subscription->cancel();

        // Reverter usuário para plano free
        $user->update([
            'plan' => 'free',
            'tokens_limit' => 100,
            'documents_limit' => 1,
        ]);

        Log::info('Subscription cancelled', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assinatura cancelada com sucesso'
        ]);
    }
}
