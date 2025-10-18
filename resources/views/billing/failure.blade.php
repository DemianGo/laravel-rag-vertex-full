@extends('layouts.app')

@section('title', 'Pagamento Falhou')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-center">
                <!-- Error Icon -->
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>

                <!-- Error Message -->
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pagamento Não Processado</h1>
                <p class="text-lg text-gray-600 mb-6">
                    {{ $error ?? 'Ocorreu um problema ao processar seu pagamento. Tente novamente.' }}
                </p>

                <!-- Possible Causes -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-yellow-900 mb-3">Possíveis Causas</h3>
                    <ul class="text-left text-yellow-800 space-y-2">
                        <li>• Dados do cartão incorretos</li>
                        <li>• Limite insuficiente no cartão</li>
                        <li>• Cartão bloqueado ou expirado</li>
                        <li>• Problema temporário com o banco</li>
                    </ul>
                </div>

                <!-- Solutions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">O que Fazer</h3>
                    <ul class="text-left text-blue-800 space-y-2">
                        <li>• Verifique os dados do cartão</li>
                        <li>• Entre em contato com seu banco</li>
                        <li>• Tente com outro cartão</li>
                        <li>• Use PIX ou boleto como alternativa</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/billing/plans" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Tentar Novamente
                    </a>
                    <a href="/dashboard" 
                       class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        </svg>
                        Voltar ao Dashboard
                    </a>
                </div>

                <!-- Support -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600">
                        Precisa de ajuda? 
                        <a href="mailto:suporte@liberai.ai" class="text-indigo-600 hover:text-indigo-500">
                            Entre em contato conosco
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
