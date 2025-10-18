@extends('layouts.app')

@section('title', 'Planos')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Escolha seu Plano</h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Desbloqueie todo o potencial da nossa plataforma RAG com planos flexíveis e transparentes
        </p>
    </div>

    <!-- Current Plan Info -->
    @if($user && $activeSubscription)
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-medium text-green-800">
                    Plano Ativo: {{ $activeSubscription->planConfig->display_name }}
                </h3>
                <p class="text-green-700">
                    Renovação: {{ $activeSubscription->ends_at ? $activeSubscription->ends_at->format('d/m/Y') : 'Automática' }}
                    @if($activeSubscription->billing_cycle === 'yearly')
                        (Anual)
                    @else
                        (Mensal)
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
        @foreach($plans as $plan)
        <div class="bg-white rounded-lg shadow-lg overflow-hidden {{ $plan->plan_name === 'pro' ? 'ring-2 ring-indigo-500 relative' : '' }}">
            @if($plan->plan_name === 'pro')
            <div class="absolute top-0 left-0 right-0 bg-indigo-500 text-white text-center py-2 text-sm font-medium">
                Mais Popular
            </div>
            @endif
            
            <div class="p-8 {{ $plan->plan_name === 'pro' ? 'pt-12' : '' }}">
                <!-- Plan Header -->
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->display_name }}</h3>
                    <p class="text-gray-600">{{ $plan->description }}</p>
                </div>

                <!-- Pricing -->
                <div class="text-center mb-8">
                    <div class="flex items-center justify-center mb-2">
                        <span class="text-4xl font-bold text-gray-900">R$</span>
                        <span class="text-6xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0, ',', '.') }}</span>
                        <span class="text-xl text-gray-600 ml-1">/mês</span>
                    </div>
                    @if($plan->price_yearly > 0)
                    <div class="text-sm text-gray-600">
                        ou R$ {{ number_format($plan->price_yearly, 0, ',', '.') }}/ano 
                        <span class="text-green-600 font-medium">({{ round(($plan->price_monthly * 12 - $plan->price_yearly) / ($plan->price_monthly * 12) * 100) }}% de desconto)</span>
                    </div>
                    @endif
                </div>

                <!-- Features -->
                <ul class="space-y-3 mb-8">
                    @if($plan->features && is_array($plan->features))
                        @foreach($plan->features as $feature)
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">{{ $feature }}</span>
                        </li>
                        @endforeach
                    @endif
                    
                    <!-- Default features based on plan -->
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-700">{{ number_format($plan->tokens_limit) }} tokens/mês</span>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-700">{{ $plan->documents_limit === 999999 ? 'Documentos ilimitados' : number_format($plan->documents_limit) . ' documentos' }}</span>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-700">Suporte técnico</span>
                    </li>
                </ul>

                <!-- CTA Button -->
                <div class="space-y-3">
                    @if($user && $activeSubscription && $activeSubscription->planConfig->id === $plan->id)
                        <button disabled class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg font-medium cursor-not-allowed">
                            Plano Atual
                        </button>
                    @elseif($user)
                        <div class="space-y-2">
                            <button onclick="selectPlan({{ $plan->id }}, 'monthly')" 
                                    class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                Assinar Mensal - R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}
                            </button>
                            @if($plan->price_yearly > 0)
                            <button onclick="selectPlan({{ $plan->id }}, 'yearly')" 
                                    class="w-full bg-green-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-green-700 transition-colors">
                                Assinar Anual - R$ {{ number_format($plan->price_yearly, 2, ',', '.') }}
                                <span class="block text-sm opacity-90">(Economize {{ round(($plan->price_monthly * 12 - $plan->price_yearly) / ($plan->price_monthly * 12) * 100) }}%)</span>
                            </button>
                            @endif
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="block w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors text-center">
                            Fazer Login para Assinar
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- FAQ Section -->
    <div class="mt-16 max-w-4xl mx-auto">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Perguntas Frequentes</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Como funciona o sistema de tokens?</h3>
                <p class="text-gray-600">Cada consulta RAG consome tokens baseado na complexidade. Planos incluem tokens mensais que são renovados automaticamente.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Posso cancelar a qualquer momento?</h3>
                <p class="text-gray-600">Sim, você pode cancelar sua assinatura a qualquer momento. O acesso permanece até o final do período pago.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Quais formas de pagamento aceitas?</h3>
                <p class="text-gray-600">Aceitamos cartão de crédito, PIX, boleto bancário e outras formas de pagamento via Mercado Pago.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Há garantia de reembolso?</h3>
                <p class="text-gray-600">Oferecemos 7 dias de garantia. Se não ficar satisfeito, reembolsamos 100% do valor pago.</p>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                <svg class="animate-spin h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-4">Processando Pagamento...</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Redirecionando para o Mercado Pago. Aguarde um momento.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function selectPlan(planId, billingCycle) {
    // Mostrar modal de loading
    document.getElementById('loadingModal').classList.remove('hidden');
    
    // Fazer requisição para processar pagamento
    fetch('/payment/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            plan_id: planId,
            billing_cycle: billingCycle
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirecionar para o Mercado Pago
            window.location.href = data.checkout_url;
        } else {
            // Esconder modal e mostrar erro
            document.getElementById('loadingModal').classList.add('hidden');
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        // Esconder modal e mostrar erro
        document.getElementById('loadingModal').classList.add('hidden');
        console.error('Error:', error);
        alert('Erro ao processar pagamento. Tente novamente.');
    });
}
</script>
@endsection
