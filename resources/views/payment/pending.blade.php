@extends('layouts.app')

@section('title', 'Pagamento Pendente')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto text-center">
        <!-- Pending Icon -->
        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-yellow-100 mb-8">
            <svg class="h-12 w-12 text-yellow-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        <!-- Pending Message -->
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Pagamento Pendente</h1>
        <p class="text-xl text-gray-600 mb-8">
            Seu pagamento está sendo processado. Isso pode levar alguns minutos.
        </p>

        @if($payment)
        <!-- Payment Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Detalhes do Pagamento</h2>
            <div class="space-y-3 text-left">
                <div class="flex justify-between">
                    <span class="text-gray-600">Plano:</span>
                    <span class="font-medium">{{ $payment->metadata['plan_name'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Valor:</span>
                    <span class="font-medium">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Forma de Pagamento:</span>
                    <span class="font-medium">{{ $payment->payment_method ?? 'Mercado Pago' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        Pendente
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Data:</span>
                    <span class="font-medium">{{ $payment->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- What's Happening -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-blue-900 mb-3">O que está acontecendo?</h3>
            <ul class="text-left text-blue-800 space-y-2">
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Seu pagamento foi recebido e está sendo verificado</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>O banco está processando a transação</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Você receberá um email assim que for aprovado</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Seus limites serão atualizados automaticamente</span>
                </li>
            </ul>
        </div>

        <!-- Expected Time -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-green-900 mb-3">Tempo Estimado</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-2xl font-bold text-green-600">PIX</div>
                    <div class="text-sm text-green-700">Até 5 minutos</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">Cartão</div>
                    <div class="text-sm text-green-700">Até 30 minutos</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">Boleto</div>
                    <div class="text-sm text-green-700">1-3 dias úteis</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-4">
            <button onclick="checkPaymentStatus()" 
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="h-5 w-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Verificar Status
            </button>
            
            <div class="text-center">
                <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">
                    ou voltar ao Dashboard
                </a>
            </div>
        </div>

        <!-- Support -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <p class="text-gray-600 mb-4">
                Tem dúvidas sobre o status do pagamento? Estamos aqui para ajudar.
            </p>
            <div class="flex justify-center space-x-6">
                <a href="mailto:suporte@liberai.ai" class="text-indigo-600 hover:text-indigo-500 font-medium">
                    📧 Email
                </a>
                <a href="#" class="text-indigo-600 hover:text-indigo-500 font-medium">
                    💬 Chat
                </a>
                <a href="#" class="text-indigo-600 hover:text-indigo-500 font-medium">
                    📚 Central de Ajuda
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function checkPaymentStatus() {
    // Simular verificação de status
    const button = document.querySelector('button[onclick="checkPaymentStatus()"]');
    const originalText = button.innerHTML;
    
    button.innerHTML = '<svg class="h-5 w-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Verificando...';
    button.disabled = true;
    
    // Recarregar a página após 2 segundos para verificar se o status mudou
    setTimeout(() => {
        window.location.reload();
    }, 2000);
}

// Auto-refresh a cada 30 segundos
setInterval(() => {
    window.location.reload();
}, 30000);
</script>
@endsection
