@extends('layouts.app')

@section('title', 'Preços - LiberAI')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                Escolha o Plano Ideal para Você
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Acesse recursos avançados de IA para processar seus documentos e obter insights inteligentes.
                Todos os planos incluem suporte completo e atualizações automáticas.
            </p>
        </div>

        <!-- Planos -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            @foreach($plans as $index => $plan)
            <div class="relative bg-white rounded-2xl shadow-lg overflow-hidden {{ $index === 1 ? 'ring-2 ring-blue-500 scale-105' : '' }}">
                @if($index === 1)
                <div class="absolute top-0 left-0 right-0 bg-blue-500 text-white text-center py-2 text-sm font-medium">
                    Mais Popular
                </div>
                @endif
                
                <div class="p-8 {{ $index === 1 ? 'pt-12' : '' }}">
                    <!-- Nome do Plano -->
                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">{{ $plan->plan_name }}</h3>
                        <p class="text-gray-600 mt-2">{{ $plan->description }}</p>
                    </div>

                    <!-- Preço -->
                    <div class="text-center mb-6">
                        <div class="flex items-center justify-center">
                            <span class="text-4xl font-bold text-gray-900">R$</span>
                            <span class="text-6xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0, ',', '.') }}</span>
                        </div>
                        <p class="text-gray-600">por mês</p>
                    </div>

                    <!-- Recursos -->
                    <div class="mb-8">
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-700">{{ number_format($plan->tokens_limit) }} tokens de IA</span>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-700">{{ $plan->documents_limit }} documentos</span>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-700">
                                    @if(is_array($plan->features))
                                        @foreach($plan->features as $feature)
                                            <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded mr-1 mb-1">{{ $feature }}</span>
                                        @endforeach
                                    @else
                                        {{ $plan->features }}
                                    @endif
                                </span>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-700">Suporte 24/7</span>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-gray-700">Atualizações automáticas</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Botão de Seleção -->
                    <div class="text-center">
                        <button onclick="openCheckoutModal('{{ $plan->plan_name }}')" 
                                class="w-full {{ $index === 1 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-800 hover:bg-gray-900' }} text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                            Escolher Plano
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Comparação de Planos -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-12">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-8">
                Comparação Detalhada dos Planos
            </h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-4 px-6 font-semibold text-gray-900">Recursos</th>
                            @foreach($plans as $plan)
                            <th class="text-center py-4 px-6 font-semibold text-gray-900">{{ $plan->plan_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="py-4 px-6 font-medium text-gray-900">Tokens de IA</td>
                            @foreach($plans as $plan)
                            <td class="text-center py-4 px-6 text-gray-700">{{ number_format($plan->tokens_limit) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="py-4 px-6 font-medium text-gray-900">Documentos</td>
                            @foreach($plans as $plan)
                            <td class="text-center py-4 px-6 text-gray-700">{{ $plan->documents_limit }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="py-4 px-6 font-medium text-gray-900">Suporte</td>
                            @foreach($plans as $plan)
                            <td class="text-center py-4 px-6 text-gray-700">24/7</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="py-4 px-6 font-medium text-gray-900">Preço Mensal</td>
                            @foreach($plans as $plan)
                            <td class="text-center py-4 px-6 text-gray-700 font-bold">R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQ -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-8">
                Perguntas Frequentes
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Posso cancelar a qualquer momento?</h3>
                    <p class="text-gray-600">Sim, você pode cancelar sua assinatura a qualquer momento. Não há taxas de cancelamento.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Os dados são seguros?</h3>
                    <p class="text-gray-600">Sim, todos os dados são criptografados e armazenados com segurança. Nunca compartilhamos suas informações.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Posso alterar meu plano?</h3>
                    <p class="text-gray-600">Sim, você pode fazer upgrade ou downgrade do seu plano a qualquer momento.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Há período de teste?</h3>
                    <p class="text-gray-600">O plano Free oferece recursos limitados para você testar a plataforma sem compromisso.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Checkout -->
<div id="checkoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">Escolher Método de Pagamento</h3>
                <button onclick="closeCheckoutModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="checkoutForm" action="{{ route('pricing.checkout') }}" method="POST">
                @csrf
                <input type="hidden" id="selectedPlan" name="plan" value="">
                
                <div class="mb-6">
                    <h4 class="text-md font-semibold text-gray-900 mb-3">Método de Pagamento:</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <label class="relative">
                            <input type="radio" name="payment_method" value="credit_card" class="peer sr-only">
                            <div class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">💳</div>
                                    <div class="text-sm font-medium">Cartão de Crédito</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="payment_method" value="pix" class="peer sr-only">
                            <div class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">📱</div>
                                    <div class="text-sm font-medium">PIX</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="payment_method" value="debit_card" class="peer sr-only">
                            <div class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">💳</div>
                                    <div class="text-sm font-medium">Débito</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="payment_method" value="boleto" class="peer sr-only">
                            <div class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">📄</div>
                                    <div class="text-sm font-medium">Boleto</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="payment_method" value="transfer" class="peer sr-only">
                            <div class="border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">🏦</div>
                                    <div class="text-sm font-medium">Transferência</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCheckoutModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Continuar para Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openCheckoutModal(planName) {
    document.getElementById('selectedPlan').value = planName;
    document.getElementById('checkoutModal').classList.remove('hidden');
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').classList.add('hidden');
}

// Fechar modal ao clicar fora
document.getElementById('checkoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCheckoutModal();
    }
});

// Validar seleção de método de pagamento
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedMethod) {
        e.preventDefault();
        alert('Por favor, selecione um método de pagamento.');
    }
});
</script>
@endsection
