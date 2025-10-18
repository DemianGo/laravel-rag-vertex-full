@extends('layouts.app')

@section('title', 'Pagamento Aprovado')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto text-center">
        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100 mb-8">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <!-- Success Message -->
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Pagamento Aprovado!</h1>
        <p class="text-xl text-gray-600 mb-8">
            Seu upgrade foi processado com sucesso. Bem-vindo ao seu novo plano!
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
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                        Aprovado
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Data:</span>
                    <span class="font-medium">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-blue-900 mb-3">Próximos Passos</h3>
            <ul class="text-left text-blue-800 space-y-2">
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Seus limites de tokens e documentos foram atualizados</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Você já pode usar todos os recursos do seu novo plano</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Receberá um email de confirmação em breve</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>A renovação será automática no final do período</span>
                </li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-4">
            <a href="{{ route('rag-frontend') }}" 
               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Começar a Usar
            </a>
            
            <div class="text-center">
                <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">
                    ou voltar ao Dashboard
                </a>
            </div>
        </div>

        <!-- Support -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <p class="text-gray-600 mb-4">
                Precisa de ajuda? Nossa equipe está aqui para você.
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
// Auto-redirect after 10 seconds (optional)
setTimeout(function() {
    if (confirm('Deseja ir para o RAG Console agora?')) {
        window.location.href = '{{ route("rag-frontend") }}';
    }
}, 10000);
</script>
@endsection
