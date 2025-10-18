@extends('layouts.app')

@section('title', 'Pagamento Pendente')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-center">
                <!-- Pending Icon -->
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                    <svg class="h-6 w-6 text-yellow-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <!-- Pending Message -->
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pagamento Pendente</h1>
                <p class="text-lg text-gray-600 mb-6">
                    Seu pagamento está sendo processado. Você receberá uma confirmação por email assim que for aprovado.
                </p>

                @if(isset($paymentId))
                <!-- Payment ID -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ID do Pagamento</h3>
                    <p class="text-sm text-gray-600 font-mono">{{ $paymentId }}</p>
                </div>
                @endif

                <!-- What Happens Next -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">O que Acontece Agora</h3>
                    <ul class="text-left text-blue-800 space-y-2">
                        <li>• Seu pagamento será verificado em até 24 horas</li>
                        <li>• Você receberá um email de confirmação</li>
                        <li>• Sua assinatura será ativada automaticamente</li>
                        <li>• Você terá acesso a todos os recursos do plano</li>
                    </ul>
                </div>

                <!-- Payment Methods Info -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-green-900 mb-3">Tempos de Processamento</h3>
                    <ul class="text-left text-green-800 space-y-2">
                        <li>• <strong>Cartão de Crédito:</strong> Aprovação imediata</li>
                        <li>• <strong>PIX:</strong> Aprovação em até 1 hora</li>
                        <li>• <strong>Boleto:</strong> Aprovação em até 3 dias úteis</li>
                        <li>• <strong>PayPal:</strong> Aprovação imediata</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button onclick="checkPaymentStatus()" 
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Verificar Status
                    </button>
                    <a href="/billing/plans" 
                       class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Escolher Outro Plano
                    </a>
                </div>

                <!-- Auto Refresh -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600">
                        Esta página será atualizada automaticamente quando o pagamento for aprovado.
                    </p>
                    <div class="mt-2">
                        <div class="inline-flex items-center text-sm text-indigo-600">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Verificando status automaticamente...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkPaymentStatus() {
    // Implementar verificação de status do pagamento
    fetch('/billing/check-status', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'approved') {
            window.location.href = '/billing/success';
        } else if (data.status === 'rejected') {
            window.location.href = '/billing/failure';
        } else {
            alert('Pagamento ainda está pendente. Tente novamente em alguns minutos.');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao verificar status do pagamento.');
    });
}

// Auto refresh a cada 30 segundos
setInterval(() => {
    checkPaymentStatus();
}, 30000);
</script>
@endsection
