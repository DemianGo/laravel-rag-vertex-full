@extends('layouts.app')

@section('title', 'Pagamento Pendente - LiberAI')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <!-- Ícone de Pendente -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-yellow-100 mb-6">
                <svg class="h-10 w-10 text-yellow-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <!-- Título -->
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Pagamento Pendente
            </h2>

            <!-- Mensagem -->
            <p class="text-lg text-gray-600 mb-6">
                Seu pagamento está sendo processado. Você receberá uma confirmação assim que for aprovado.
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Pendente
                        </span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Informações sobre Pagamentos Pendentes -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-medium text-blue-800 mb-2">O que acontece agora?</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Seu pagamento está sendo analisado</li>
                    <li>• Você receberá um e-mail quando for aprovado</li>
                    <li>• O processo pode levar alguns minutos</li>
                    <li>• Em caso de problemas, entraremos em contato</li>
                </ul>
            </div>

            <!-- Botões de Ação -->
            <div class="space-y-4">
                <a href="{{ route('rag-frontend') }}" 
                   class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    Acessar Plataforma
                </a>
                
                <a href="{{ route('pricing.index') }}" 
                   class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    Ver Planos
                </a>
            </div>

            <!-- Informações de Suporte -->
            <div class="mt-8 text-sm text-gray-500">
                <p>Você pode acompanhar o status do seu pagamento em seu painel.</p>
                <p>Em caso de dúvidas, entre em contato conosco.</p>
            </div>
        </div>
    </div>
</div>
@endsection
