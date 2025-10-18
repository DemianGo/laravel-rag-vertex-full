@extends('admin.layouts.app')

@section('title', 'Detalhes do Documento - Admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <div class="flex space-x-4">
            <a href="javascript:history.back()" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Voltar
            </a>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
                </svg>
                Dashboard Admin
            </a>
        </div>
    </div>

    <!-- Document Info -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-12 w-12">
                    <div class="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $document->title ?? 'Document #' . $document->id }}</h3>
                    <p class="text-sm text-gray-600">{{ $document->source ?? 'upload' }} • {{ isset($document->created_at) ? \Carbon\Carbon::parse($document->created_at)->format('M j, Y') : 'Unknown date' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-700">Total Chunks</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">
                        {{ count($chunks) }}
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-700">Status</div>
                    <div class="text-2xl font-bold {{ count($chunks) > 0 ? 'text-green-600' : 'text-yellow-600' }} mt-1">
                        {{ count($chunks) > 0 ? 'Processed' : 'Processing' }}
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-700">Document ID</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">#{{ $document->id }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-700">Tenant</div>
                    <div class="text-sm font-bold text-gray-900 mt-1">{{ $document->tenant_slug ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="mt-6 flex space-x-4">
                <a href="{{ route('admin.documents.download', $document->id) }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Arquivo Original
                </a>
                
                <button onclick="toggleMetadata()" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Ver Metadados
                </button>
            </div>

            <!-- Metadata Section (Hidden by default) -->
            <div id="metadataSection" class="mt-6 hidden">
                <h4 class="text-lg font-medium text-gray-900 mb-3">Metadados do Documento</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <pre class="text-sm text-gray-700 overflow-auto">{{ json_encode(json_decode($document->metadata ?? '{}'), JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Chunks -->
    @if(count($chunks) > 0)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Document Chunks ({{ count($chunks) }})</h3>
                <div class="space-y-4">
                    @foreach($chunks as $chunk)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="text-sm font-medium text-gray-900">Chunk #{{ ($chunk->ord ?? 0) + 1 }}</h4>
                                <div class="text-xs text-gray-500">
                                    {{ strlen($chunk->content ?? '') }} characters
                                </div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded text-sm text-gray-700 leading-relaxed">
                                {{ Str::limit($chunk->content ?? 'Empty chunk', 500) }}
                            </div>
                            @if(isset($chunk->metadata) && $chunk->metadata)
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="text-xs text-gray-500">
                                        <strong>Metadata:</strong> {{ $chunk->metadata }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if(count($chunks) > 10)
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-500">Showing all {{ count($chunks) }} chunks for this document.</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No chunks found</h3>
                <p class="mt-1 text-sm text-gray-500">This document may still be processing or there was an issue during processing.</p>
            </div>
        </div>
    @endif

    <!-- Help Section -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Admin View - Document Details</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p>
                        Esta é a visualização administrativa do documento. Como admin, você tem acesso a todos os documentos do sistema.
                        Use o botão "Download Arquivo Original" para baixar o arquivo original, ou visualize os chunks extraídos abaixo.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleMetadata() {
    const section = document.getElementById('metadataSection');
    section.classList.toggle('hidden');
}
</script>
@endsection
