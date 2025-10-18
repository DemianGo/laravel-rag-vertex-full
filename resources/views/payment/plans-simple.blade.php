@extends('layouts.app')

@section('title', 'Planos')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">Escolha seu Plano</h1>
    
    @if($user && $activeSubscription && $activeSubscription->planConfig)
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
        <h3 class="text-lg font-medium text-green-800">
            Plano Ativo: {{ $activeSubscription->planConfig->display_name }}
        </h3>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($plans as $plan)
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->display_name }}</h3>
                <div class="text-6xl font-bold text-gray-900 mb-4">
                    R$ {{ number_format($plan->price_monthly, 0, ',', '.') }}
                </div>
                <p class="text-gray-600 mb-6">{{ $plan->description }}</p>
                
                @if($user && $activeSubscription && $activeSubscription->planConfig && $activeSubscription->planConfig->id === $plan->id)
                    <button disabled class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg font-medium cursor-not-allowed">
                        Plano Atual
                    </button>
                @elseif($user)
                    <button onclick="selectPlan({{ $plan->id }}, 'monthly')" 
                            class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                        Assinar - R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}
                    </button>
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

<script>
function selectPlan(planId, billingCycle) {
    alert('Plan selected: ' + planId + ' (' + billingCycle + ')');
}
</script>
@endsection
