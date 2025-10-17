<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Documents & RAG Console') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Tab Navigation -->
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                        <a href="{{ route('documents.index', ['tab' => 'ingest']) }}" 
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'ingest' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            📥 Ingest
                        </a>
                        <a href="{{ route('documents.index', ['tab' => 'python-rag']) }}" 
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'python-rag' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            🤖 Python RAG
                        </a>
                        <a href="{{ route('documents.index', ['tab' => 'metrics']) }}" 
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'metrics' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            📊 Metrics
                        </a>
                    </nav>
                </div>
            </div>

            @if($tab === 'ingest')
                @include('documents.tabs.ingest')
            @elseif($tab === 'python-rag')
                @include('documents.tabs.python-rag')
            @elseif($tab === 'metrics')
                @include('documents.tabs.metrics')
            @else
                @include('documents.tabs.ingest')
            @endif

        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="{{ asset('rag-frontend/file-validator.js') }}"></script>
    <script src="{{ asset('rag-frontend/rag-client.js') }}"></script>
    
    <!-- Initialize -->
    <script>
        window.Laravel = {
            user: @json($user),
            tenant_slug: 'user_{{ $user->id }}',
            api_token: '{{ $user->api_key ?? '' }}',
            baseUrl: '{{ url('/') }}'
        };
    </script>

</x-app-layout>
