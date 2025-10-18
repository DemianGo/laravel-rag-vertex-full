<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planos - {{ config('app.name') }}</title>
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
                        @guest
                            <a href="{{ route('login') }}" class="text-gray-700 hover:text-gray-900">Login</a>
                            <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">Registrar</a>
                        @else
                            <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-gray-900">Dashboard</a>
                        @endguest
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">Escolha seu Plano</h1>
                    <p class="text-lg text-gray-600">Selecione o plano perfeito para suas necessidades</p>
                </div>

                @if($user && $activeSubscription && $activeSubscription->planConfig)
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-medium text-green-800">
                        Plano Ativo: {{ $activeSubscription->planConfig->display_name }}
                    </h3>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach($plans as $plan)
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="p-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->display_name }}</h3>
                            <div class="mb-4">
                                <span class="text-5xl font-bold text-gray-900">R$ {{ number_format($plan->price_monthly, 0, ',', '.') }}</span>
                                <span class="text-gray-600">/mês</span>
                            </div>
                            <p class="text-gray-600 mb-6 min-h-[60px]">{{ $plan->description ?? 'Plano ' . $plan->display_name }}</p>
                            
                            <!-- Features -->
                            @if($plan->features && is_array($plan->features))
                            <ul class="space-y-3 mb-6">
                                @foreach($plan->features as $feature)
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ $feature }}</span>
                                </li>
                                @endforeach
                            </ul>
                            @endif

                            <!-- Action Button -->
                            @if($user && $activeSubscription && $activeSubscription->planConfig && $activeSubscription->planConfig->id === $plan->id)
                                <button disabled class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg font-medium cursor-not-allowed">
                                    Plano Atual
                                </button>
                            @elseif($user)
                                <form action="{{ route('payment.checkout') }}" method="GET" class="w-full">
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <input type="hidden" name="billing_cycle" value="monthly">
                                    <button type="submit" class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                        Assinar Agora
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="block w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors text-center">
                                    Fazer Login para Assinar
                                </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</body>
</html>

