@extends('admin.layouts.app')

@section('title', 'Gerenciar Planos')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Gerenciar Planos</h2>
                    <button onclick="openCreateModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        Criar Novo Plano
                    </button>
                </div>

                <!-- Plans Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plano</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preço Mensal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preço Anual</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Limites</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margem</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($plans as $plan)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <span class="text-indigo-600 font-medium">{{ substr($plan->display_name, 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $plan->display_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $plan->plan_name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    R$ {{ number_format($plan->price_monthly, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    R$ {{ number_format($plan->price_yearly, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="font-medium">{{ $plan->tokens_limit == -1 ? 'Ilimitado' : number_format($plan->tokens_limit) }} tokens</div>
                                    <div class="font-medium">{{ $plan->documents_limit == -1 ? 'Ilimitado' : number_format($plan->documents_limit) }} docs</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $plan->margin_percentage > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ number_format($plan->margin_percentage, 1) }}%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($plan->is_active)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Ativo
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editPlan({{ $plan->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                                    <button onclick="togglePlan({{ $plan->id }}, {{ $plan->is_active ? 'false' : 'true' }})" 
                                            class="text-{{ $plan->is_active ? 'red' : 'green' }}-600 hover:text-{{ $plan->is_active ? 'red' : 'green' }}-900">
                                        {{ $plan->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Statistics -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-900">Total de Planos</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ $plans->count() }}</p>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-900">Planos Ativos</h3>
                        <p class="text-3xl font-bold text-green-600">{{ $plans->where('is_active', true)->count() }}</p>
                    </div>
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-purple-900">Margem Média</h3>
                        <p class="text-3xl font-bold text-purple-600">{{ number_format($plans->avg('margin_percentage'), 1) }}%</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="planModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Criar Novo Plano</h3>
                
                <form id="planForm">
                    <input type="hidden" id="planId" name="id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome do Plano</label>
                            <input type="text" id="planName" name="plan_name" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome para Exibição</label>
                            <input type="text" id="displayName" name="display_name" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Preço Mensal</label>
                                <input type="number" id="priceMonthly" name="price_monthly" step="0.01" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Preço Anual</label>
                                <input type="number" id="priceYearly" name="price_yearly" step="0.01" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Limite de Tokens</label>
                                <input type="number" id="tokensLimit" name="tokens_limit" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Limite de Documentos</label>
                                <input type="number" id="documentsLimit" name="documents_limit" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Margem de Lucro (%)</label>
                            <input type="number" id="marginPercentage" name="margin_percentage" step="0.1" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descrição</label>
                            <textarea id="description" name="description" rows="3" 
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Features (uma por linha)</label>
                            <textarea id="features" name="features" rows="4" placeholder="Exemplo:&#10;100 tokens por mês&#10;1 documento&#10;Suporte por email"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            <p class="mt-1 text-sm text-gray-500">Digite uma feature por linha</p>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="isActive" name="is_active" 
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label class="ml-2 block text-sm text-gray-900">Plano Ativo</label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Criar Novo Plano';
    document.getElementById('planForm').reset();
    document.getElementById('planId').value = '';
    document.getElementById('planModal').classList.remove('hidden');
}

async function editPlan(planId) {
    try {
        const response = await fetch(`/admin/plans/${planId}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const plan = data.plan;
            
            // Preencher o formulário com os dados do plano
            document.getElementById('modalTitle').textContent = 'Editar Plano';
            document.getElementById('planId').value = plan.id;
            document.getElementById('planName').value = plan.plan_name || '';
            document.getElementById('displayName').value = plan.display_name || '';
            document.getElementById('priceMonthly').value = plan.price_monthly || '';
            document.getElementById('priceYearly').value = plan.price_yearly || '';
            document.getElementById('tokensLimit').value = plan.tokens_limit || '';
            document.getElementById('documentsLimit').value = plan.documents_limit || '';
            document.getElementById('marginPercentage').value = plan.margin_percentage || '';
            document.getElementById('description').value = plan.description || '';
            document.getElementById('isActive').checked = plan.is_active;
            
            // Converter features de JSON string para array
            if (plan.features) {
                try {
                    const features = JSON.parse(plan.features);
                    if (Array.isArray(features)) {
                        document.getElementById('features').value = features.join('\n');
                    }
                } catch (e) {
                    console.log('Features não é um JSON válido');
                }
            }
            
            document.getElementById('planModal').classList.remove('hidden');
        } else {
            alert('Erro ao carregar dados do plano');
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao carregar dados do plano');
    }
}

function closeModal() {
    document.getElementById('planModal').classList.add('hidden');
}

async function togglePlan(planId, isActive) {
    if (confirm(`Tem certeza que deseja ${isActive === 'true' ? 'ativar' : 'desativar'} este plano?`)) {
        try {
            const response = await fetch(`/admin/plans/${planId}/toggle`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao alterar status do plano');
        }
    }
}

document.getElementById('planForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const planId = document.getElementById('planId').value;
    const isEdit = planId !== '';
    
    // Converter FormData para objeto
    const data = {};
    formData.forEach((value, key) => {
        if (key === 'is_active') {
            data[key] = document.getElementById('is_active').checked;
        } else if (key === 'features') {
            // Converter features de texto para array
            const featuresText = value.trim();
            if (featuresText) {
                data[key] = featuresText.split('\n').filter(f => f.trim() !== '');
            } else {
                data[key] = [];
            }
        } else {
            data[key] = value;
        }
    });
    
    try {
        const url = isEdit ? `/admin/plans/${planId}` : '/admin/plans';
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeModal();
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao salvar plano');
    }
});

// Adicionar token CSRF ao cabeçalho da página
document.head.insertAdjacentHTML('beforeend', '<meta name="csrf-token" content="{{ csrf_token() }}">');
</script>
@endsection
