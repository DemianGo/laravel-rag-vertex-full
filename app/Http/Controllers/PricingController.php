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

        // Se usuário não estiver logado, redirecionar para login
        if (!Auth::check()) {
            return redirect()->route('login')->with('intended', route('pricing.checkout', [
                'plan' => $request->plan,
                'payment_method' => $request->payment_method
            ]));
        }

        $user = Auth::user();

        try {
            // Criar preferência de pagamento no Mercado Pago
            $preference = $this->mercadoPagoService->createPreference([
                'items' => [
                    [
                        'title' => "Plano {$plan->plan_name}",
                        'quantity' => 1,
                        'unit_price' => $plan->price_monthly,
                        'currency_id' => 'BRL'
                    ]
                ],
                'payer' => [
                    'email' => $user->email,
                    'name' => $user->name
                ],
                'external_reference' => "user_{$user->id}_plan_{$plan->plan_name}",
                'notification_url' => route('webhook.mercadopago'),
                'back_urls' => [
                    'success' => route('pricing.success'),
                    'failure' => route('pricing.failure'),
                    'pending' => route('pricing.pending')
                ],
                'payment_methods' => [
                    'excluded_payment_types' => $this->getExcludedPaymentTypes($request->payment_method),
                    'installments' => $request->payment_method === 'credit_card' ? 12 : 1
                ]
            ]);

            // Redirecionar para o checkout do Mercado Pago
            return redirect($preference['init_point']);

        } catch (\Exception $e) {
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

    /**
     * Obter tipos de pagamento excluídos baseado no método selecionado
     */
    private function getExcludedPaymentTypes(string $paymentMethod): array
    {
        $allTypes = ['credit_card', 'debit_card', 'ticket', 'bank_transfer', 'digital_wallet'];
        
        switch ($paymentMethod) {
            case 'credit_card':
                return array_diff($allTypes, ['credit_card']);
            case 'debit_card':
                return array_diff($allTypes, ['debit_card']);
            case 'pix':
                return array_diff($allTypes, ['digital_wallet']);
            case 'boleto':
                return array_diff($allTypes, ['ticket']);
            case 'transfer':
                return array_diff($allTypes, ['bank_transfer']);
            default:
                return [];
        }
    }
}
