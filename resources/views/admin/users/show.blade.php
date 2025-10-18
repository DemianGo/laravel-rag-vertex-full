@extends('admin.layouts.app')

@section('title', 'Detalhes do Usuário')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Detalhes do Usuário</h1>
                    <p class="text-gray-600">{{ $user->name }} ({{ $user->email }})</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.users.index') }}" 
                       class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        ← Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- User Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Basic Info -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informações Básicas</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Nome</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Plano Atual</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $user->plan === 'enterprise' ? 'bg-purple-100 text-purple-800' : 
                                       ($user->plan === 'pro' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst($user->plan ?? 'free') }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tipo de Usuário</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($user->is_super_admin)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        Super Admin
                                    </span>
                                @elseif($user->is_admin)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Admin
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Usuário
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Registrado em</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Último Login</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca' }}
                                @if($user->last_login_ip)
                                    <span class="text-gray-500">({{ $user->last_login_ip }})</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Usage Stats -->
        <div>
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Uso do Sistema</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Tokens -->
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Tokens</span>
                                <span class="font-medium">{{ $user->tokens_used ?? 0 }} / {{ $user->tokens_limit ?? 100 }}</span>
                            </div>
                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ min(100, (($user->tokens_used ?? 0) / ($user->tokens_limit ?? 100)) * 100) }}%"></div>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Documentos</span>
                                <span class="font-medium">{{ $user->documents_used ?? 0 }} / {{ $user->documents_limit ?? 1 }}</span>
                            </div>
                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(100, (($user->documents_used ?? 0) / ($user->documents_limit ?? 1)) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin())
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Ações Administrativas</h4>
                        <div class="space-y-2">
                            @if(!$user->is_super_admin || auth()->user()->isSuperAdmin())
                            <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded">
                                    {{ $user->is_admin ? 'Remover Admin' : 'Tornar Admin' }}
                                </button>
                            </form>
                            @endif
                            
                            @if(auth()->user()->isSuperAdmin() && $user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.toggle-super-admin', $user) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded">
                                    {{ $user->is_super_admin ? 'Remover Super Admin' : 'Tornar Super Admin' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriptions -->
    @if($user->subscriptions->count() > 0)
    <div class="bg-white shadow-sm sm:rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Assinaturas</h3>
        </div>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plano</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Início</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fim</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($user->subscriptions as $subscription)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $subscription->planConfig->display_name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : 
                                   ($subscription->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($subscription->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            R$ {{ number_format($subscription->amount, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $subscription->starts_at ? $subscription->starts_at->format('d/m/Y') : 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $subscription->ends_at ? $subscription->ends_at->format('d/m/Y') : 'N/A' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Payments -->
    @if($user->payments->count() > 0)
    <div class="bg-white shadow-sm sm:rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Pagamentos</h3>
        </div>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($user->payments as $payment)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $payment->formatted_amount }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $payment->status === 'approved' ? 'bg-green-100 text-green-800' : 
                                   ($payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ $payment->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $payment->payment_method ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $payment->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Documents -->
    <div class="bg-white shadow-sm sm:rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Documentos do Usuário</h3>
                <div class="text-sm text-gray-500">
                    Total: <span id="documentCount">{{ $documents->count() }}</span> documentos
                </div>
            </div>
        </div>
        <div class="p-6 w-full">
            @if($documents->count() > 0)
            <!-- DataTables Table -->
            <div class="w-full">
                <div id="documentsTable_wrapper" class="dataTables_wrapper">
                    <table id="documentsTable" class="table table-striped table-bordered w-full" style="width:100% !important">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Tipo/Fonte</th>
                        <th>URI</th>
                        <th>Tenant</th>
                        <th>Chunks</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $document)
                    <tr>
                        <td>{{ $document->id }}</td>
                        <td>
                            <div class="font-medium text-gray-900">{{ $document->title }}</div>
                            @if($document->metadata)
                                @php
                                    $metadata = is_string($document->metadata) ? json_decode($document->metadata, true) : $document->metadata;
                                @endphp
                                @if(isset($metadata['file_size']))
                                    <div class="text-xs text-gray-500">{{ number_format($metadata['file_size'] / 1024, 1) }} KB</div>
                                @endif
                            @endif
                        </td>
                        <td>
                            @php
                                $source = $document->source ?? 'unknown';
                                $icon = match(strtolower($source)) {
                                    'pdf' => '📄',
                                    'docx', 'doc' => '📝',
                                    'xlsx', 'xls' => '📊',
                                    'pptx', 'ppt' => '📋',
                                    'txt' => '📃',
                                    'csv' => '📈',
                                    'html' => '🌐',
                                    'url' => '🔗',
                                    default => '📁'
                                };
                            @endphp
                            <span class="text-lg">{{ $icon }}</span>
                            <span class="ml-1 text-sm text-gray-600">{{ ucfirst($source) }}</span>
                        </td>
                        <td>
                            @if($document->uri)
                                <div class="text-xs text-gray-500 max-w-xs truncate" title="{{ $document->uri }}">
                                    {{ Str::limit($document->uri, 50) }}
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $document->tenant_slug }}
                            </span>
                        </td>
                        <td>
                            @php
                                $chunksCount = \App\Models\Chunk::where('document_id', $document->id)->count();
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $chunksCount > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $chunksCount }} chunks
                            </span>
                        </td>
                        <td>
                            <div class="text-sm text-gray-900">{{ $document->created_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $document->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="/admin/documents/{{ $document->id }}" 
                                   class="btn-view"
                                   title="Visualizar documento">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Ver
                                </a>
                                
                                @if($document->uri && (str_contains($document->uri, 'http') || str_contains($document->source, 'url')))
                                <a href="{{ $document->uri }}" 
                                   class="btn-open"
                                   target="_blank"
                                   title="Abrir URI original">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Abrir
                                </a>
                                @endif
                                
                                <button onclick="deleteDocument({{ $document->id }}, '{{ $document->title }}')" 
                                        class="btn-delete"
                                        title="Deletar documento completamente">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Deletar
                                </button>

                                <button onclick="showDocumentDetails({{ $document->id }})" 
                                        class="btn-info"
                                        title="Ver detalhes">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Info
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                </table>
                </div>
            </div>
            @else
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum documento</h3>
                <p class="mt-1 text-sm text-gray-500">Este usuário ainda não fez upload de documentos.</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Document Details Modal -->
    <div id="documentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <h3 id="modalTitle" class="text-lg font-medium text-gray-900 mb-4">Detalhes do Documento</h3>
                    <div id="modalContent" class="space-y-4">
                        <!-- Content will be loaded here -->
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button onclick="closeDocumentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
           /* Custom DataTables styling */
           #documentsTable {
               font-size: 0.875rem;
               width: 100% !important;
               min-width: 100% !important;
           }

           /* Fix table wrapper width */
           #documentsTable_wrapper {
               width: 100% !important;
               min-width: 100% !important;
           }

           /* Fix table-responsive */
           .table-responsive {
               width: 100% !important;
               min-width: 100% !important;
               overflow-x: auto;
           }

           /* Ensure full width for table container */
           .dataTables_wrapper {
               width: 100% !important;
               min-width: 100% !important;
           }

           /* Force table to use full width */
           #documentsTable_wrapper .dataTables_scrollBody {
               width: 100% !important;
           }

           #documentsTable_wrapper .dataTables_scrollHeadInner {
               width: 100% !important;
           }

           #documentsTable_wrapper .dataTables_scrollHeadInner table {
               width: 100% !important;
           }

           /* Fix table positioning */
           .dataTables_wrapper .dataTables_length,
           .dataTables_wrapper .dataTables_filter,
           .dataTables_wrapper .dataTables_info,
           .dataTables_wrapper .dataTables_processing,
           .dataTables_wrapper .dataTables_paginate {
               width: 100% !important;
               text-align: left;
           }

           /* Ensure table takes full container width */
           .dataTables_wrapper .dataTables_scroll {
               width: 100% !important;
           }
    
    #documentsTable th {
        background-color: #f9fafb;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
        color: #6b7280;
        padding: 0.75rem;
    }
    
    #documentsTable td {
        vertical-align: middle;
        padding: 0.75rem;
    }
    
    /* Fix DataTables responsive layout */
    .dataTables_wrapper {
        width: 100%;
        position: relative;
        overflow: hidden;
    }
    
    .dataTables_length,
    .dataTables_filter {
        display: inline-block;
        width: auto !important;
        margin-bottom: 1rem;
    }
    
    .dataTables_length {
        float: left;
    }
    
    .dataTables_filter {
        float: right;
        text-align: right;
    }
    
    .dataTables_filter input {
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        margin-left: 0.5rem;
        width: 200px;
    }
    
    .dataTables_length select {
        padding: 0.25rem 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.25rem;
        margin: 0 0.5rem;
        width: auto;
    }
    
    /* Fix pagination layout */
    .dataTables_info {
        float: left;
        margin-top: 1rem;
        clear: both;
    }
    
    .dataTables_paginate {
        float: right;
        margin-top: 1rem;
        text-align: right;
        position: relative;
        z-index: 1;
    }
    
    /* Ensure pagination stays within wrapper */
    .dataTables_wrapper::after {
        content: "";
        display: table;
        clear: both;
    }
    
    /* Fix pagination positioning */
    #documentsTable_wrapper {
        position: relative;
        width: 100%;
        overflow: hidden;
    }
    
    #documentsTable_wrapper .dataTables_paginate {
        position: relative;
        float: right;
        margin-top: 1rem;
        text-align: right;
        clear: both;
    }
    
    #documentsTable_wrapper .dataTables_info {
        position: relative;
        float: left;
        margin-top: 1rem;
        clear: both;
    }
    
    .dataTables_paginate .paginate_button {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        margin: 0 0.125rem;
        border-radius: 0.375rem;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        transition: all 0.2s ease;
        text-decoration: none;
        min-width: auto;
    }
    
    .dataTables_paginate .paginate_button:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        text-decoration: none;
    }
    
    .dataTables_paginate .paginate_button.current {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }
    
    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Custom control buttons */
    .dt-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
        clear: both;
    }
    
    .dt-controls button {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        text-decoration: none;
    }
    
    .dt-controls .btn-csv {
        background-color: #10b981;
        color: white;
    }
    
    .dt-controls .btn-csv:hover {
        background-color: #059669;
    }
    
    .dt-controls .btn-excel {
        background-color: #3b82f6;
        color: white;
    }
    
    .dt-controls .btn-excel:hover {
        background-color: #2563eb;
    }
    
    .dt-controls .btn-pdf {
        background-color: #ef4444;
        color: white;
    }
    
    .dt-controls .btn-pdf:hover {
        background-color: #dc2626;
    }
    
    .dt-controls .btn-toggle {
        background-color: #6b7280;
        color: white;
    }
    
    .dt-controls .btn-toggle:hover {
        background-color: #4b5563;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dataTables_length,
        .dataTables_filter {
            float: none;
            display: block;
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        .dataTables_filter {
            text-align: left;
        }
        
        .dataTables_filter input {
            width: 100%;
            margin-left: 0;
            margin-top: 0.5rem;
        }
        
        .dt-controls {
            justify-content: flex-start;
        }
        
        .dt-controls button {
            flex: 1;
            justify-content: center;
            min-width: 80px;
        }
        
        .dataTables_info,
        .dataTables_paginate {
            float: none;
            text-align: center;
            margin-top: 1rem;
        }
        
        .dataTables_paginate .paginate_button {
            margin: 0.125rem;
        }
    }
    
    /* Table responsive wrapper */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Action buttons in table */
    .action-buttons {
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
    }
    
    .action-buttons a,
    .action-buttons button {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 0.25rem;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .action-buttons .btn-view {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .action-buttons .btn-view:hover {
        background-color: #bfdbfe;
    }
    
    .action-buttons .btn-open {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .action-buttons .btn-open:hover {
        background-color: #bfdbfe;
    }
    
    .action-buttons .btn-delete {
        background-color: #fecaca;
        color: #dc2626;
    }
    
    .action-buttons .btn-delete:hover {
        background-color: #fca5a5;
    }
    
    .action-buttons .btn-info {
        background-color: #f3f4f6;
        color: #374151;
    }
    
    .action-buttons .btn-info:hover {
        background-color: #e5e7eb;
    }
</style>
@endsection

@section('scripts')
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with proper configuration
    const table = $('#documentsTable').DataTable({
        responsive: false, // Disable responsive to ensure full width
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        order: [[6, 'desc']], // Sort by created_at desc by default
        autoWidth: false, // Disable auto width calculation
        scrollX: false, // Disable horizontal scroll
        fixedColumns: false, // Disable fixed columns
        columnDefs: [
            {
                targets: [0], // ID column
                visible: false // Hide ID column by default
            },
            {
                targets: [7], // Actions column
                orderable: false,
                searchable: false
            }
        ],
        language: {
            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        dom: '<"dt-controls-wrapper"<"dt-controls"B>>' +
             '<"dataTables_length"l>' +
             '<"dataTables_filter"f>' +
             '<"table-responsive"tr>' +
             '<"dataTables_info"i>' +
             '<"dataTables_paginate"p>',
        buttons: [
            {
                extend: 'csv',
                className: 'btn-csv',
                text: '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>CSV',
                title: 'Documentos do Usuário'
            },
            {
                extend: 'excel',
                className: 'btn-excel',
                text: '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>Excel',
                title: 'Documentos do Usuário'
            },
            {
                extend: 'pdf',
                className: 'btn-pdf',
                text: '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>PDF',
                title: 'Documentos do Usuário',
                orientation: 'landscape',
                pageSize: 'A4'
            },
            {
                text: '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>Mostrar IDs',
                className: 'btn-toggle',
                action: function(e, dt, node, config) {
                    const column = dt.column(0);
                    const isVisible = column.visible();
                    
                    column.visible(!isVisible);
                    
                    if (isVisible) {
                        $(node).html('<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>Mostrar IDs');
                    } else {
                        $(node).html('<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path></svg>Ocultar IDs');
                    }
                }
            }
        ],
        initComplete: function() {
            // Update document count
            updateDocumentCount();
            
            // Style the buttons
            $('.dt-buttons').addClass('dt-controls');
        }
    });
});

function updateDocumentCount() {
    const table = $('#documentsTable').DataTable();
    const totalRecords = table.data().length;
    $('#documentCount').text(totalRecords);
}

function showDocumentDetails(documentId) {
    // Show loading
    $('#modalContent').html(`
        <div class="flex justify-center items-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <span class="ml-2 text-gray-600">Carregando detalhes...</span>
        </div>
    `);
    
    $('#documentModal').removeClass('hidden');
    
    // Fetch real document data
    fetch(`/api/docs/${documentId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            // The API returns the document directly, not wrapped in success/document
            if (data && data.id) {
                const doc = data;
                $('#modalContent').html(`
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-900">Informações do Documento</h4>
                            <div class="mt-2 space-y-2">
                                <p><span class="font-medium">ID:</span> ${doc.id}</p>
                                <p><span class="font-medium">Título:</span> ${doc.title}</p>
                                <p><span class="font-medium">Tipo:</span> ${doc.source}</p>
                                <p><span class="font-medium">Tenant:</span> ${doc.tenant_slug}</p>
                                <p><span class="font-medium">Criado em:</span> ${new Date(doc.created_at).toLocaleString('pt-BR')}</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Metadados</h4>
                            <pre class="text-xs bg-gray-100 p-2 rounded overflow-auto max-h-40">${JSON.stringify(doc.metadata || {}, null, 2)}</pre>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Ações</h4>
                            <div class="space-y-2">
                                <a href="/admin/documents/${doc.id}/download" class="block w-full text-left px-3 py-2 text-sm bg-green-50 text-green-700 rounded hover:bg-green-100 transition-colors">
                                    📥 Download Arquivo Original
                                </a>
                                <a href="/admin/documents/${doc.id}" class="block w-full text-left px-3 py-2 text-sm bg-indigo-50 text-indigo-700 rounded hover:bg-indigo-100 transition-colors">
                                    📄 Ver Documento (Admin)
                                </a>
                                ${doc.uri && (doc.uri.includes('http') || doc.source === 'url') ? 
                                    `<a href="${doc.uri}" target="_blank" class="block w-full text-left px-3 py-2 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100 transition-colors">
                                        🔗 Abrir URI Original
                                    </a>` : ''
                                }
                            </div>
                        </div>
                    </div>
                `);
            } else {
                $('#modalContent').html(`
                    <div class="text-center py-4">
                        <p class="text-red-600">Erro ao carregar detalhes do documento.</p>
                        <p class="text-sm text-gray-500 mt-2">${data.message || data.error || 'Documento não encontrado.'}</p>
                    </div>
                `);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            $('#modalContent').html(`
                <div class="text-center py-4">
                    <p class="text-red-600">Erro ao carregar detalhes do documento.</p>
                    <p class="text-sm text-gray-500 mt-2">Verifique sua conexão e tente novamente.</p>
                </div>
            `);
        });
}

// Function to delete document with confirmation
function deleteDocument(documentId, documentTitle) {
    // Show confirmation dialog
    if (!confirm(`⚠️ ATENÇÃO: Esta ação é IRREVERSÍVEL!\n\nVocê está prestes a deletar o documento:\n"${documentTitle}"\n\nIsso irá:\n• Deletar o arquivo do disco\n• Remover todos os chunks do banco\n• Remover todos os feedbacks\n• Liberar espaço em disco\n• Remover o registro do documento\n\nTem certeza que deseja continuar?`)) {
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = `
        <svg class="w-3 h-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Deletando...
    `;
    button.disabled = true;
    
    // Make DELETE request
    fetch(`/admin/documents/${documentId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(`✅ Documento deletado com sucesso!\n\nDetalhes:\n• ID: ${data.document_id}\n• Título: ${data.document_title}\n• Arquivo deletado: ${data.file_deleted ? 'Sim' : 'Não'}\n• Espaço liberado: ${data.space_freed ? 'Sim' : 'Não'}\n\nA página será recarregada para atualizar a lista.`);
            
            // Reload the page to update the documents list
            window.location.reload();
        } else {
            // Show error message
            alert(`❌ Erro ao deletar documento:\n\n${data.error || 'Erro desconhecido'}`);
            
            // Restore button
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(`❌ Erro de conexão ao deletar documento:\n\n${error.message}`);
        
        // Restore button
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function closeDocumentModal() {
    $('#documentModal').addClass('hidden');
}

// Close modal when clicking outside
$(document).on('click', '#documentModal', function(e) {
    if (e.target === this) {
        closeDocumentModal();
    }
});

// Close modal with Escape key
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && !$('#documentModal').hasClass('hidden')) {
        closeDocumentModal();
    }
});
</script>
@endsection
