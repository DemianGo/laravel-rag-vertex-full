@extends('admin.layouts.app')

@section('title', 'Provedores de IA')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold">Provedores de IA</h2>
                        <p class="text-gray-600 mt-1">Configure custos e margens para cada modelo de IA</p>
                    </div>
                    <button onclick="openCreateModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        Adicionar Provedor
                    </button>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0 sm:space-x-4">
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700">Provedor:</label>
                                <select id="providerFilter" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos os provedores</option>
                                    <option value="openai">OpenAI</option>
                                    <option value="gemini">Google Gemini</option>
                                    <option value="claude">Anthropic Claude</option>
                                </select>
                            </div>
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700">Status:</label>
                                <select id="statusFilter" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos os status</option>
                                    <option value="active">Ativos</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <input type="text" id="searchInput" placeholder="Buscar provedores..." 
                                       class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                       onkeyup="searchProviders(this.value)">
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="exportFilteredData('csv')" 
                                        class="text-sm bg-green-100 text-green-700 px-3 py-1 rounded-md hover:bg-green-200">
                                    📊 CSV
                                </button>
                                <button onclick="exportFilteredData('json')" 
                                        class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-md hover:bg-blue-200">
                                    📄 JSON
                                </button>
                            </div>
                            <div id="resultCounter" class="text-sm text-gray-600">
                                Carregando...
                            </div>
                            <button onclick="clearFilters()" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                Limpar Filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Providers Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('provider')">
                                    Provedor <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('model')">
                                    Modelo <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('cost')">
                                    Custos (USD/1K) <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('markup')">
                                    Margem <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('context')">
                                    Contexto <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                    onclick="sortProviders('status')">
                                    Status <span class="sort-indicator">↕️</span>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="providersTable" class="bg-white divide-y divide-gray-200">
                            @foreach($providers as $provider)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <span class="text-indigo-600 font-medium">{{ strtoupper(substr($provider->provider_name, 0, 1)) }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ ucfirst($provider->provider_name) }}</div>
                                            @if($provider->is_default)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Padrão</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium">{{ $provider->display_name }}</div>
                                    <div class="text-gray-500">{{ $provider->model_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>Entrada: ${{ number_format($provider->input_cost_per_1k, 6) }}</div>
                                    <div>Saída: ${{ number_format($provider->output_cost_per_1k, 6) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium">{{ $provider->base_markup_percentage }}%</div>
                                    <div class="text-xs text-gray-500">{{ $provider->min_markup_percentage }}% - {{ $provider->max_markup_percentage }}%</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ number_format($provider->context_length) }} tokens
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $provider->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $provider->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editProvider({{ $provider->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                                    <button onclick="toggleProvider({{ $provider->id }}, {{ $provider->is_active ? 'true' : 'false' }})" 
                                            class="text-{{ $provider->is_active ? 'red' : 'green' }}-600 hover:text-{{ $provider->is_active ? 'red' : 'green' }}-900">
                                        {{ $provider->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Statistics -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-900">Total de Modelos</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ $providers->count() }}</p>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-900">Modelos Ativos</h3>
                        <p class="text-3xl font-bold text-green-600">{{ $providers->where('is_active', true)->count() }}</p>
                    </div>
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-purple-900">Margem Média</h3>
                        <p class="text-3xl font-bold text-purple-600">{{ number_format($providers->avg('base_markup_percentage'), 1) }}%</p>
                    </div>
                    <div class="bg-orange-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-orange-900">Custo Médio/1K</h3>
                        <p class="text-3xl font-bold text-orange-600">${{ number_format($providers->avg('input_cost_per_1k'), 6) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="providerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Adicionar Provedor de IA</h3>
                
                <form id="providerForm">
                    <input type="hidden" id="providerId" name="id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Provedor</label>
                            <select id="providerName" name="provider_name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Selecione...</option>
                                <option value="openai">OpenAI</option>
                                <option value="gemini">Google Gemini</option>
                                <option value="claude">Anthropic Claude</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome do Modelo</label>
                            <input type="text" id="modelName" name="model_name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome para Exibição</label>
                            <input type="text" id="displayName" name="display_name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tamanho do Contexto</label>
                            <input type="number" id="contextLength" name="context_length" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Custo Entrada (USD/1K tokens)</label>
                            <input type="number" id="inputCost" name="input_cost_per_1k" step="0.000001" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Custo Saída (USD/1K tokens)</label>
                            <input type="number" id="outputCost" name="output_cost_per_1k" step="0.000001" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Margem Base (%)</label>
                            <input type="number" id="baseMarkup" name="base_markup_percentage" step="0.01" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Margem Mínima (%)</label>
                            <input type="number" id="minMarkup" name="min_markup_percentage" step="0.01" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Margem Máxima (%)</label>
                            <input type="number" id="maxMarkup" name="max_markup_percentage" step="0.01" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ordem</label>
                            <input type="number" id="sortOrder" name="sort_order" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="md:col-span-2 flex items-center space-x-6">
                            <label class="flex items-center">
                                <input type="checkbox" id="isActive" name="is_active" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-900">Ativo</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="isDefault" name="is_default" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-900">Provedor Padrão</span>
                            </label>
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

console.log('🔧 JavaScript carregado para provedores de IA');

// Variáveis globais para filtros
let allProviders = [];
let filteredProviders = [];

// Carregar dados iniciais
document.addEventListener('DOMContentLoaded', function() {
    loadProviders();
    initializeFilters();
});

// Inicializar filtros
function initializeFilters() {
    const providerFilter = document.getElementById('providerFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    // Adicionar event listeners
    providerFilter.addEventListener('change', filterProviders);
    statusFilter.addEventListener('change', filterProviders);
    
    console.log('✅ Filtros inicializados');
}

// Função para carregar provedores
async function loadProviders() {
    try {
        const response = await fetch('/admin/ai-providers/data', {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            allProviders = data.providers;
            filteredProviders = [...allProviders];
            renderProviders();
            updateStats(data.stats);
            console.log('✅ Provedores carregados:', allProviders.length);
        }
    } catch (error) {
        console.error('❌ Erro ao carregar provedores:', error);
    }
}

// Função para filtrar provedores
function filterProviders() {
    const providerFilter = document.getElementById('providerFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    console.log('🔍 Aplicando filtros:', { providerFilter, statusFilter });
    
    filteredProviders = allProviders.filter(provider => {
        const matchesProvider = !providerFilter || provider.provider_name === providerFilter;
        const matchesStatus = !statusFilter || 
            (statusFilter === 'active' && provider.is_active) ||
            (statusFilter === 'inactive' && !provider.is_active);
        
        return matchesProvider && matchesStatus;
    });
    
    renderProviders();
    updateFilterStats();
    console.log('✅ Filtros aplicados:', filteredProviders.length, 'de', allProviders.length);
}

// Função para renderizar provedores na tabela
function renderProviders() {
    const tbody = document.querySelector('#providersTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    filteredProviders.forEach(provider => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                            <span class="text-indigo-600 font-medium">${provider.provider_name.charAt(0).toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${provider.provider_name}</div>
                        ${provider.is_default ? '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Padrão</span>' : ''}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <div class="font-medium">${provider.display_name}</div>
                <div class="text-gray-500">${provider.model_name}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div>Entrada: $${provider.input_cost_per_1k.toFixed(6)}</div>
                <div>Saída: $${provider.output_cost_per_1k.toFixed(6)}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <div class="font-medium">${provider.base_markup_percentage}%</div>
                <div class="text-xs text-gray-500">${provider.min_markup_percentage}% - ${provider.max_markup_percentage}%</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${provider.context_length.toLocaleString()} tokens
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${provider.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                    ${provider.is_active ? 'Ativo' : 'Inativo'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="editProvider(${provider.id})" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                <button onclick="toggleProvider(${provider.id}, ${provider.is_active})" 
                        class="text-${provider.is_active ? 'red' : 'green'}-600 hover:text-${provider.is_active ? 'red' : 'green'}-900">
                    ${provider.is_active ? 'Desativar' : 'Ativar'}
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Função para atualizar estatísticas
function updateStats(stats) {
    const totalElement = document.getElementById('totalModels');
    const activeElement = document.getElementById('activeModels');
    const markupElement = document.getElementById('averageMarkup');
    const costElement = document.getElementById('averageCost');
    
    if (totalElement) totalElement.textContent = stats.total;
    if (activeElement) activeElement.textContent = stats.active;
    if (markupElement) markupElement.textContent = `${stats.average_markup.toFixed(2)}%`;
    if (costElement) costElement.textContent = `$${stats.average_cost.toFixed(6)}`;
}

// Função para atualizar estatísticas dos filtros
function updateFilterStats() {
    const activeCount = filteredProviders.filter(p => p.is_active).length;
    const totalCount = filteredProviders.length;
    
    // Atualizar contador de resultados
    const resultCounter = document.getElementById('resultCounter');
    if (resultCounter) {
        resultCounter.textContent = `Mostrando ${totalCount} de ${allProviders.length} provedores`;
    }
    
    console.log('📊 Estatísticas dos filtros:', { total: totalCount, active: activeCount });
}

// Função para limpar filtros
function clearFilters() {
    document.getElementById('providerFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterProviders();
    console.log('🧹 Filtros limpos');
}

// Função para buscar provedores por texto
function searchProviders(searchTerm) {
    if (!searchTerm) {
        filterProviders();
        return;
    }
    
    const term = searchTerm.toLowerCase();
    filteredProviders = allProviders.filter(provider => {
        return provider.provider_name.toLowerCase().includes(term) ||
               provider.model_name.toLowerCase().includes(term) ||
               provider.display_name.toLowerCase().includes(term);
    });
    
    renderProviders();
    updateFilterStats();
    console.log('🔍 Busca realizada:', searchTerm, 'resultados:', filteredProviders.length);
}

// Função para ordenar provedores
function sortProviders(column, direction = 'asc') {
    filteredProviders.sort((a, b) => {
        let aVal, bVal;
        
        switch(column) {
            case 'provider':
                aVal = a.provider_name;
                bVal = b.provider_name;
                break;
            case 'model':
                aVal = a.display_name;
                bVal = b.display_name;
                break;
            case 'cost':
                aVal = a.input_cost_per_1k;
                bVal = b.input_cost_per_1k;
                break;
            case 'markup':
                aVal = a.base_markup_percentage;
                bVal = b.base_markup_percentage;
                break;
            case 'context':
                aVal = a.context_length;
                bVal = b.context_length;
                break;
            case 'status':
                aVal = a.is_active ? 1 : 0;
                bVal = b.is_active ? 1 : 0;
                break;
            default:
                return 0;
        }
        
        if (direction === 'desc') {
            return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
        } else {
            return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
        }
    });
    
    renderProviders();
    console.log('📊 Ordenação aplicada:', column, direction);
}

// Função para exportar dados filtrados
function exportFilteredData(format) {
    const data = filteredProviders.map(provider => ({
        'Provedor': provider.provider_name,
        'Modelo': provider.display_name,
        'Custo Entrada': provider.input_cost_per_1k,
        'Custo Saída': provider.output_cost_per_1k,
        'Margem Base': provider.base_markup_percentage,
        'Contexto': provider.context_length,
        'Status': provider.is_active ? 'Ativo' : 'Inativo',
        'Padrão': provider.is_default ? 'Sim' : 'Não'
    }));
    
    if (format === 'json') {
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'provedores_ia_filtrados.json';
        a.click();
        URL.revokeObjectURL(url);
    } else if (format === 'csv') {
        const csv = convertToCSV(data);
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'provedores_ia_filtrados.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    
    console.log('📤 Exportação realizada:', format, 'registros:', data.length);
}

// Função auxiliar para converter para CSV
function convertToCSV(data) {
    if (data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => `"${row[header]}"`).join(','))
    ].join('\n');
    
    return csvContent;
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Adicionar Provedor de IA';
    document.getElementById('providerForm').reset();
    document.getElementById('providerId').value = '';
    document.getElementById('providerModal').classList.remove('hidden');
}

async function editProvider(providerId) {
    try {
        const response = await fetch(`/admin/ai-providers/${providerId}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const provider = data.provider;
            
            document.getElementById('modalTitle').textContent = 'Editar Provedor de IA';
            document.getElementById('providerId').value = provider.id;
            document.getElementById('providerName').value = provider.provider_name;
            document.getElementById('modelName').value = provider.model_name;
            document.getElementById('displayName').value = provider.display_name;
            document.getElementById('inputCost').value = provider.input_cost_per_1k;
            document.getElementById('outputCost').value = provider.output_cost_per_1k;
            document.getElementById('contextLength').value = provider.context_length;
            document.getElementById('baseMarkup').value = provider.base_markup_percentage;
            document.getElementById('minMarkup').value = provider.min_markup_percentage;
            document.getElementById('maxMarkup').value = provider.max_markup_percentage;
            document.getElementById('sortOrder').value = provider.sort_order;
            document.getElementById('isActive').checked = provider.is_active;
            document.getElementById('isDefault').checked = provider.is_default;
            
            document.getElementById('providerModal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao carregar provedor:', error);
        alert('Erro ao carregar dados do provedor');
    }
}

function closeModal() {
    document.getElementById('providerModal').classList.add('hidden');
}

async function toggleProvider(providerId, isActive) {
    if (confirm(`Tem certeza que deseja ${isActive ? 'desativar' : 'ativar'} este provedor?`)) {
        try {
            const response = await fetch(`/admin/ai-providers/${providerId}/toggle`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(data.message);
                loadProviders();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao alterar status do provedor');
        }
    }
}

document.getElementById('providerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const providerId = document.getElementById('providerId').value;
    const isEdit = providerId !== '';
    
    const data = {
        provider_name: formData.get('provider_name'),
        model_name: formData.get('model_name'),
        display_name: formData.get('display_name'),
        input_cost_per_1k: parseFloat(formData.get('input_cost_per_1k')),
        output_cost_per_1k: parseFloat(formData.get('output_cost_per_1k')),
        context_length: parseInt(formData.get('context_length')),
        base_markup_percentage: parseFloat(formData.get('base_markup_percentage')),
        min_markup_percentage: parseFloat(formData.get('min_markup_percentage')),
        max_markup_percentage: parseFloat(formData.get('max_markup_percentage')),
        sort_order: parseInt(formData.get('sort_order')) || 0,
        is_active: document.getElementById('isActive').checked,
        is_default: document.getElementById('isDefault').checked
    };
    
    try {
        const url = isEdit ? `/admin/ai-providers/${providerId}` : '/admin/ai-providers';
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
            loadProviders();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao salvar provedor');
    }
});
</script>
@endsection
