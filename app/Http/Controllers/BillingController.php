<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Models\User;
use App\Models\PlanConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    private BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Exibe a página de planos
     */
    public function plans()
    {
        $plans = PlanConfig::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $user = Auth::user();
        $activeSubscription = $user->activeSubscription();

        return view('billing.plans', compact('plans', 'user', 'activeSubscription'));
    }

    /**
     * Processa seleção de plano
     */
    public function selectPlan(Request $request)
    {
        $request->validate([
            'plan_name' => 'required|string|exists:plan_configs,plan_name'
        ]);

        $user = Auth::user();
        $planName = $request->plan_name;

        // Verificar se o usuário já tem este plano
        if ($user->plan === $planName) {
            return response()->json([
                'success' => false,
                'error' => 'Você já possui este plano'
            ]);
        }

        // Processar pagamento
        $result = $this->billingService->processPayment($user, $planName);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'checkout_url' => $result['checkout_url'],
                'amount' => $result['amount'],
                'plan' => $result['plan']
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error']
        ]);
    }

    /**
     * Página de sucesso do pagamento
     */
    public function success(Request $request)
    {
        $paymentId = $request->query('payment_id');
        $preferenceId = $request->query('preference_id');

        if ($paymentId) {
            $payment = \App\Models\Payment::find($paymentId);
            if ($payment && $payment->status === 'approved') {
                return view('billing.success', compact('payment'));
            }
        }

        return view('billing.success');
    }

    /**
     * Página de falha do pagamento
     */
    public function failure(Request $request)
    {
        $error = $request->query('error', 'Pagamento não foi processado');
        return view('billing.failure', compact('error'));
    }

    /**
     * Página de pagamento pendente
     */
    public function pending(Request $request)
    {
        $paymentId = $request->query('payment_id');
        return view('billing.pending', compact('paymentId'));
    }

    /**
     * Webhook do Mercado Pago
     */
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('Webhook recebido', ['data' => $data]);

            $result = $this->billingService->processWebhook($data);

            if ($result['success']) {
                return response()->json(['status' => 'success', 'message' => $result['message'] ?? 'Webhook processado']);
            }

            return response()->json(['status' => 'error', 'message' => $result['error']], 400);

        } catch (\Exception $e) {
            Log::error('Erro no webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Erro interno'], 500);
        }
    }

    /**
     * Cobra tokens de um usuário (API)
     */
    public function chargeTokens(Request $request)
    {
        $request->validate([
            'tokens' => 'required|integer|min:1',
            'operation' => 'string|max:255'
        ]);

        $user = Auth::user();
        $tokens = $request->tokens;
        $operation = $request->operation ?? 'ai_usage';

        $result = $this->billingService->chargeUserTokens($user, $tokens, $operation);

        return response()->json($result);
    }

    /**
     * Calcula custo de IA (API)
     */
    public function calculateAiCost(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'model' => 'required|string',
            'input_tokens' => 'required|integer|min:0',
            'output_tokens' => 'required|integer|min:0'
        ]);

        $result = $this->billingService->calculateAiCost(
            $request->provider,
            $request->model,
            $request->input_tokens,
            $request->output_tokens
        );

        return response()->json($result);
    }

    /**
     * Obtém estatísticas de cobrança (Admin)
     */
    public function stats()
    {
        $stats = $this->billingService->getBillingStats();
        return response()->json($stats);
    }

    /**
     * Cancela assinatura
     */
    public function cancelSubscription(Request $request, $subscriptionId)
    {
        $result = $this->billingService->cancelSubscription($subscriptionId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error']
        ], 400);
    }

    /**
     * Testa sistema de cobrança
     */
    public function testBilling()
    {
        $user = Auth::user();
        
        // Teste 1: Calcular custo de IA
        $aiCost = $this->billingService->calculateAiCost('openai', 'gpt-4', 1000, 500);
        
        // Teste 2: Cobrar tokens
        $chargeResult = $this->billingService->chargeUserTokens($user, 10, 'test_operation');
        
        // Teste 3: Obter estatísticas
        $stats = $this->billingService->getBillingStats();

        return response()->json([
            'success' => true,
            'tests' => [
                'ai_cost_calculation' => $aiCost,
                'token_charge' => $chargeResult,
                'billing_stats' => $stats
            ],
            'user_tokens' => [
                'used' => $user->tokens_used,
                'limit' => $user->tokens_limit,
                'remaining' => $user->tokens_limit - $user->tokens_used
            ]
        ]);
    }
}
