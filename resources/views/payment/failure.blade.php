@extends('layouts.app')

@section('title', 'Pagamento Não Processado')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto text-center">
        <!-- Failure Icon -->
        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-red-100 mb-8">
            <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>

        <!-- Failure Message -->
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Pagamento Não Processado</h1>
        <p class="text-xl text-gray-600 mb-8">
            Não foi possível processar seu pagamento. Não se preocupe, nenhuma cobrança foi realizada.
        </p>

        @if($payment)
        <!-- Payment Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Detalhes da Tentativa</h2>
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
                    <span class="text-gray-600">Status:</span>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                        {{ ucfirst($payment->status) }}
                    </span>
                </div>
                @if($payment->failure_reason)
                <div class="flex justify-between">
                    <span class="text-gray-600">Motivo:</span>
                    <span class="font-medium text-red-600">{{ $payment->failure_reason }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-600">Data:</span>
                    <span class="font-medium">{{ $payment->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Possible Causes -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-yellow-900 mb-3">Possíveis Causas</h3>
            <ul class="text-left text-yellow-800 space-y-2">
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-yellow-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Dados do cartão incorretos ou expirados</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-yellow-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Limite do cartão insuficiente</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-yellow-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Problemas de conectividade</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-yellow-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Bloqueio temporário do banco</span>
                </li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-4">
            <a href="{{ route('payment.plans') }}" 
               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Tentar Novamente
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
                Ainda com problemas? Nossa equipe está aqui para ajudar.
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

        <!-- Alternative Payment Methods -->
        <div class="mt-8 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Outras Formas de Pagamento</h3>
            <p class="text-gray-600 mb-4">
                Se continuar com problemas, você pode tentar:
            </p>
            <ul class="text-left text-gray-700 space-y-2">
                <li>• Usar outro cartão de crédito ou débito</li>
                <li>• Tentar pagamento via PIX (mais rápido)</li>
                <li>• Usar boleto bancário</li>
                <li>• Entrar em contato para pagamento via transferência</li>
            </ul>
        </div>
    </div>
</div>
@endsection
