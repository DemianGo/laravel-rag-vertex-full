<?php

namespace App\Http\Controllers;

use App\Models\PlanConfig;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PricingController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Exibir página de preços
     */
    public function index()
    {
        $plans = PlanConfig::where('is_active', true)
            ->orderBy('price_monthly')
            ->get();

        return view('pricing.index', compact('plans'));
    }

    /**
     * Processar checkout
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'plan' => 'required|exists:plan_configs,plan_name',
            'payment_method' => 'required|in:credit_card,pix,debit_card,transfer,boleto'
        ]);

        $plan = PlanConfig::where('plan_name', $request->plan)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return redirect()->back()->with('error', 'Plano não encontrado ou inativo.');
        }

        // Dados do usuário (logado ou não)
        $userEmail = Auth::check() ? Auth::user()->email : 'guest@liberai.com';
        $userName = Auth::check() ? Auth::user()->name : 'Cliente';
        $userId = Auth::check() ? Auth::user()->id : null;

        try {
            // Criar preferência de pagamento no Mercado Pago
            $preference = $this->mercadoPagoService->createPreference([
                'title' => "Plano {$plan->plan_name}",
                'amount' => $plan->price_monthly,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'external_reference' => "user_{$userId}_plan_{$plan->plan_name}",
                'notification_url' => route('webhook.mercadopago'),
                'success_url' => route('pricing.success'),
                'failure_url' => route('pricing.failure'),
                'pending_url' => route('pricing.pending')
            ]);

            // Redirecionar para o checkout do Mercado Pago
            return redirect($preference['init_point']);

        } catch (\Exception $e) {
            // Se for erro de token inválido, simular checkout para teste
            if (strpos($e->getMessage(), 'invalid_token') !== false) {
                return redirect()->route('pricing.success')->with('success', 'Checkout simulado com sucesso! (Modo teste - configure as chaves do Mercado Pago para produção)');
            }
            
            // Em caso de erro, mostrar informações de debug
            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Erro ao processar pagamento',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
            return redirect()->back()->with('error', 'Erro ao processar pagamento: ' . $e->getMessage());
        }
    }

    /**
     * Página de sucesso do pagamento
     */
    public function success(Request $request)
    {
        return view('pricing.success', [
            'payment_id' => $request->get('payment_id'),
            'status' => $request->get('status')
        ]);
    }

    /**
     * Página de falha do pagamento
     */
    public function failure(Request $request)
    {
        return view('pricing.failure', [
            'payment_id' => $request->get('payment_id'),
            'status' => $request->get('status')
        ]);
    }

    /**
     * Página de pagamento pendente
     */
    public function pending(Request $request)
    {
        return view('pricing.pending', [
            'payment_id' => $request->get('payment_id'),
            'status' => $request->get('status')
        ]);
    }

}
