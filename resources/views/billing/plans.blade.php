@extends('layouts.app')

@section('title', 'Planos e Assinaturas')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Escolha seu Plano</h1>
            <p class="mt-2 text-lg text-gray-600">Aumente sua produtividade com nossos planos de IA</p>
        </div>

        <!-- Current Plan Status -->
        @if($activeSubscription)
        <div class="mb-8 bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 00-.293.707V11a1 1 0 102 0v-1.586l2.293-2.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">
                        Plano Ativo: {{ $activeSubscription->planConfig->display_name }}
                    </h3>
                    <div class="mt-1 text-sm text-green-700">
                        <p>Tokens: {{ $user->tokens_used }} / {{ $user->tokens_limit }}</p>
                        <p>Documentos: {{ $user->documents_used }} / {{ $user->documents_limit }}</p>
                        <p>Expira em: {{ $activeSubscription->ends_at->format('d/m/Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Plans Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($plans as $plan)
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 {{ $user->plan === $plan->plan_name ? 'ring-2 ring-indigo-500' : '' }}">
                <!-- Plan Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">{{ $plan->display_name }}</h3>
                        @if($user->plan === $plan->plan_name)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Plano Atual
                        </span>
                        @endif
                    </div>
                    <div class="mt-4">
                        <span class="text-4xl font-bold text-gray-900">R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}</span>
                        <span class="text-gray-600">/mês</span>
                    </div>
                </div>

                <!-- Plan Features -->
                <div class="p-6">
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">{{ number_format($plan->tokens_limit) }} tokens</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">{{ $plan->documents_limit }} documentos</span>
                        </li>
                        @if($plan->features)
                        @php
                            $features = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;
                        @endphp
                        @foreach($features as $feature)
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">{{ $feature }}</span>
                        </li>
                        @endforeach
                        @endif
                    </ul>
                </div>

                <!-- Plan Actions -->
                <div class="p-6 border-t border-gray-200">
                    @if($user->plan === $plan->plan_name)
                    <button disabled class="w-full bg-gray-300 text-gray-500 py-2 px-4 rounded-md cursor-not-allowed">
                        Plano Atual
                    </button>
                    @else
                    <button onclick="selectPlan('{{ $plan->plan_name }}')" 
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition-colors">
                        {{ $user->plan === 'free' ? 'Fazer Upgrade' : 'Alterar Plano' }}
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Billing Information -->
        <div class="mt-12 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Informações de Cobrança</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Como Funciona</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Cobrança mensal automática</li>
                        <li>• Cancele a qualquer momento</li>
                        <li>• Suporte 24/7 incluído</li>
                        <li>• Atualizações automáticas</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Métodos de Pagamento</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Cartão de crédito</li>
                        <li>• PIX</li>
                        <li>• Boleto bancário</li>
                        <li>• PayPal</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-gray-900">Processando...</h3>
                    <p class="text-sm text-gray-600">Redirecionando para o pagamento</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPlan(planName) {
    // Mostrar loading
    document.getElementById('loadingModal').classList.remove('hidden');
    
    // Fazer requisição
    fetch('/billing/select-plan', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            plan_name: planName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirecionar para checkout
            window.location.href = data.checkout_url;
        } else {
            // Mostrar erro
            alert('Erro: ' + data.error);
            document.getElementById('loadingModal').classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar solicitação');
        document.getElementById('loadingModal').classList.add('hidden');
    });
}
</script>
@endsection
