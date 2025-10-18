@extends('layouts.app')

@section('title', 'Pagamento Aprovado')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-center">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <!-- Success Message -->
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pagamento Aprovado!</h1>
                <p class="text-lg text-gray-600 mb-6">
                    Sua assinatura foi ativada com sucesso. Agora você tem acesso a todos os recursos do seu plano.
                </p>

                @if(isset($payment))
                <!-- Payment Details -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Detalhes do Pagamento</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Valor:</span>
                            <span class="ml-2 text-gray-900">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Status:</span>
                            <span class="ml-2 text-green-600 font-medium">Aprovado</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Data:</span>
                            <span class="ml-2 text-gray-900">{{ $payment->paid_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Método:</span>
                            <span class="ml-2 text-gray-900">{{ ucfirst($payment->payment_method) }}</span>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Next Steps -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">Próximos Passos</h3>
                    <ul class="text-left text-blue-800 space-y-2">
                        <li>• Seus tokens foram resetados e você tem acesso ao limite completo</li>
                        <li>• Você pode fazer upload de mais documentos</li>
                        <li>• Acesse o RAG Console para usar a IA</li>
                        <li>• Sua assinatura será renovada automaticamente</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/rag-frontend" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Usar RAG Console
                    </a>
                    <a href="/documents" 
                       class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Gerenciar Documentos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
