@extends('layouts.app')

@section('title', 'Pagamento Falhou - LiberAI')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <!-- Ícone de Erro -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6">
                <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <!-- Título -->
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Pagamento Não Processado
            </h2>

            <!-- Mensagem -->
            <p class="text-lg text-gray-600 mb-6">
                Ocorreu um problema ao processar seu pagamento. Por favor, tente novamente ou escolha outro método de pagamento.
            </p>

            <!-- Detalhes do Pagamento -->
            @if($payment_id)
            <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalhes do Pagamento</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">ID do Pagamento:</span>
                        <span class="font-medium text-gray-900">{{ $payment_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Falhou
                        </span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Possíveis Causas -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-medium text-yellow-800 mb-2">Possíveis causas:</h4>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Dados do cartão incorretos</li>
                    <li>• Saldo insuficiente</li>
                    <li>• Cartão bloqueado ou expirado</li>
                    <li>• Problemas de conectividade</li>
                </ul>
            </div>

            <!-- Botões de Ação -->
            <div class="space-y-4">
                <a href="{{ route('pricing.index') }}" 
                   class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    Tentar Novamente
                </a>
                
                <a href="{{ route('rag-frontend') }}" 
                   class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    Voltar à Plataforma
                </a>
            </div>

            <!-- Informações de Suporte -->
            <div class="mt-8 text-sm text-gray-500">
                <p>Se o problema persistir, entre em contato conosco:</p>
                <p class="font-medium">📧 suporte@liberai.ai</p>
                <p class="font-medium">📱 (11) 99999-9999</p>
            </div>
        </div>
    </div>
</div>
@endsection
