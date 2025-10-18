<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - {{ $plan->display_name }} - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="/" class="text-xl font-bold text-gray-900">
                            {{ config('app.name', 'Laravel') }}
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('payment.plans') }}" class="text-gray-700 hover:text-gray-900">← Voltar aos Planos</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="py-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- Plan Summary -->
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Resumo do Plano</h2>
                        
                        <div class="border rounded-lg p-6 mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold">{{ $plan->display_name }}</h3>
                                <span class="text-2xl font-bold text-indigo-600">
                                    R$ {{ number_format($amount, 2, ',', '.') }}
                                </span>
                            </div>
                            
                            <div class="text-sm text-gray-600 mb-4">
                                Cobrança: {{ $billingCycle === 'yearly' ? 'Anual' : 'Mensal' }}
                                @if($billingCycle === 'yearly')
                                    <span class="text-green-600 font-medium">(2 meses grátis!)</span>
                                @endif
                            </div>
                            
                            @if($plan->features && is_array($plan->features))
                            <ul class="space-y-2">
                                @foreach($plan->features as $feature)
                                <li class="flex items-start text-sm">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span>{{ $feature }}</span>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </div>

                        <!-- Fee Breakdown -->
                        @if($fees)
                        <div class="border-t pt-4">
                            <h4 class="font-medium text-gray-900 mb-2">Detalhamento</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span>Plano {{ $billingCycle === 'yearly' ? 'Anual' : 'Mensal' }}:</span>
                                    <span>R$ {{ number_format($amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Taxa Mercado Pago:</span>
                                    <span>R$ {{ number_format($fees['mercadopago_fee'] ?? 0, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Taxa LiberAI:</span>
                                    <span>R$ {{ number_format($fees['platform_fee'] ?? 0, 2, ',', '.') }}</span>
                                </div>
                                <div class="border-t pt-1 flex justify-between font-medium">
                                    <span>Total:</span>
                                    <span>R$ {{ number_format($amount + ($fees['total_fees'] ?? 0), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Payment Form -->
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Finalizar Pagamento</h2>
                        
                        @if($user)
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-800">
                                    <strong>Logado como:</strong> {{ $user->name }} ({{ $user->email }})
                                </p>
                            </div>
                        @endif

                        <!-- Mercado Pago Integration -->
                        <div id="payment-form-container">
                            <div class="text-center py-8">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                                <p class="text-gray-600">Carregando formulário de pagamento...</p>
                            </div>
                        </div>

                        <!-- Manual Payment Button (Fallback) -->
                        <div class="mt-6">
                            <button onclick="processPayment()" 
                                    class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                Processar Pagamento
                            </button>
                        </div>

                        <!-- Security Notice -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="text-sm text-gray-600">
                                    <p class="font-medium text-gray-900">Pagamento Seguro</p>
                                    <p>Seus dados são protegidos com criptografia SSL e processados pelo Mercado Pago.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mercado Pago Configuration
        const mpConfig = @json($mpConfig);
        
        async function processPayment() {
            try {
                const response = await fetch('{{ route("payment.process") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        plan_id: {{ $plan->id }},
                        billing_cycle: '{{ $billingCycle }}'
                    })
                });

                const data = await response.json();
                
                if (data.success && data.checkout_url) {
                    // Redirect to Mercado Pago checkout
                    window.location.href = data.checkout_url;
                } else {
                    alert('Erro: ' + (data.error || 'Não foi possível processar o pagamento'));
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('Erro ao processar pagamento. Tente novamente.');
            }
        }

        // Initialize Mercado Pago if credentials are available
        @if($mpConfig && $mpConfig.publicKey)
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof MercadoPago !== 'undefined') {
                const mercadopago = new MercadoPago(mpConfig.publicKey);
                
                // Here you would initialize the card form
                // For now, we'll show a simple button
                document.getElementById('payment-form-container').innerHTML = `
                    <div class="text-center py-4">
                        <p class="text-gray-600 mb-4">Clique no botão abaixo para finalizar o pagamento</p>
                        <button onclick="processPayment()" 
                                class="bg-green-600 text-white py-3 px-8 rounded-lg font-medium hover:bg-green-700 transition-colors">
                            Pagar com Mercado Pago
                        </button>
                    </div>
                `;
            } else {
                // Fallback for when Mercado Pago SDK is not loaded
                document.getElementById('payment-form-container').innerHTML = `
                    <div class="text-center py-4">
                        <p class="text-gray-600 mb-4">Mercado Pago SDK não carregado. Use o botão manual.</p>
                    </div>
                `;
            }
        });
        @endif
    </script>
</body>
</html>
