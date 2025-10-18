<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PlanConfig;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Página de planos
     */
    public function plans()
    {
        try {
            $plans = PlanConfig::active()->ordered()->get();
            $user = Auth::user();
            $activeSubscription = null;
            
            if ($user) {
                try {
                    $activeSubscription = $user->activeSubscription();
                } catch (Exception $e) {
                    // Ignore errors in activeSubscription method
                    $activeSubscription = null;
                }
            }
            
            // Configurações do Mercado Pago para o frontend
            $mpConfig = $this->mercadoPagoService->getFrontendConfig();

            return view('payment.plans-basic', compact('plans', 'user', 'activeSubscription', 'mpConfig'));
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Página de checkout específico
     */
    public function checkout(Request $request)
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
            return redirect()->route('payment.plans')
                ->with('error', 'Você já possui este plano ativo');
        }

        // Configurações do Mercado Pago
        $mpConfig = $this->mercadoPagoService->getFrontendConfig();
        $amount = $request->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $fees = $this->mercadoPagoService->calculateFees($amount);

        $billingCycle = $request->billing_cycle;
        
        return view('payment.checkout', compact(
            'plan', 
            'user', 
            'activeSubscription', 
            'mpConfig',
            'billingCycle',
            'amount',
            'fees'
        ));
    }

    /**
     * Inicia processo de pagamento via AJAX
     */
    public function process(Request $request)
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

        try {
            // Criar preferência no Mercado Pago
            $preferenceData = [
                'title' => "Upgrade para {$plan->display_name}",
                'amount' => $request->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'external_reference' => "checkout_{$user->id}_" . time(),
                'success_url' => route('payment.success'),
                'failure_url' => route('payment.failure'),
                'pending_url' => route('payment.pending'),
                'notification_url' => route('payment.webhook')
            ];

            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            return response()->json([
                'success' => true,
                'checkout_url' => $preference['init_point'],
                'preference_id' => $preference['id'],
                'amount' => $preferenceData['amount'],
                'plan' => $plan->display_name
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar pagamento. Tente novamente.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Retorna configurações do Mercado Pago para o frontend
     */
    public function config()
    {
        return response()->json($this->mercadoPagoService->getFrontendConfig());
    }
}
