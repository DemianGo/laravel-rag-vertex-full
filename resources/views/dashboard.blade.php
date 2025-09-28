<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium">Welcome back, {{ $user->name }}!</h3>
                            <p class="text-gray-600 mt-1">Your RAG platform dashboard</p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Current Plan</div>
                            <div class="text-lg font-medium {{ $user->plan === 'enterprise' ? 'text-purple-600' : ($user->plan === 'pro' ? 'text-blue-600' : 'text-gray-600') }}">
                                {{ ucfirst($user->plan) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Token Usage -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-500">Token Usage</h4>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold text-gray-900">{{ $user->tokens_used }}</div>
                                    <div class="text-sm text-gray-500">of {{ $user->tokens_limit }}</div>
                                </div>
                                <div class="mt-3">
                                    <div class="bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(($user->tokens_used / $user->tokens_limit) * 100, 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Usage -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-500">Documents</h4>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold text-gray-900">{{ $user->documents_used }}</div>
                                    <div class="text-sm text-gray-500">of {{ $user->documents_limit }}</div>
                                </div>
                                <div class="mt-3">
                                    <div class="bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(($user->documents_used / $user->documents_limit) * 100, 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-500">System Status</h4>
                                <div class="mt-2">
                                    <div class="flex items-center">
                                        @if($health['ok'] ?? false)
                                            <div class="h-3 w-3 bg-green-400 rounded-full mr-2"></div>
                                            <span class="text-sm text-green-600">Online</span>
                                        @else
                                            <div class="h-3 w-3 bg-red-400 rounded-full mr-2"></div>
                                            <span class="text-sm text-red-600">Offline</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <svg class="h-8 w-8 {{ ($health['ok'] ?? false) ? 'text-green-600' : 'text-red-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <a href="{{ route('documents.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Upload Document
                        </a>
                        <a href="{{ route('upload-bypass.index') }}" class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 active:bg-orange-900 focus:outline-none focus:border-orange-900 focus:ring ring-orange-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            🚀 Upload Rápido
                        </a>
                        <a href="{{ route('chat.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            Start Chat
                        </a>
                        <a href="{{ route('plans.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-900 focus:outline-none focus:border-purple-900 focus:ring ring-purple-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Upgrade Plan
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Documents -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Documents</h3>
                        @if(count($documents) > 0)
                            <div class="space-y-3">
                                @foreach(array_slice($documents, 0, 5) as $doc)
                                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900">{{ $doc['title'] ?? 'Untitled' }}</h4>
                                            <p class="text-xs text-gray-500">{{ $doc['chunks_count'] ?? 0 }} chunks</p>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ isset($doc['created_at']) ? \Carbon\Carbon::parse($doc['created_at'])->diffForHumans() : 'Recently' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-sm">No documents uploaded yet. <a href="{{ route('documents.index') }}" class="text-blue-600 hover:text-blue-500">Upload your first document</a></p>
                        @endif
                    </div>
                </div>

                <!-- Cache Statistics -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">System Statistics</h3>
                        @if($cacheStats['ok'] ?? false)
                            <div class="space-y-3">
                                @foreach($cacheStats['cache_stats'] ?? [] as $key => $value)
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                        <span class="text-sm font-medium text-gray-900">{{ is_numeric($value) ? number_format($value) : $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-sm">Cache statistics unavailable</p>
                        @endif

                        @if($embeddingStats['ok'] ?? false)
                            <div class="mt-4 pt-4 border-t">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Embedding Cache</h4>
                                @foreach($embeddingStats['embedding_cache'] ?? [] as $key => $value)
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                        <span class="text-sm font-medium text-gray-900">{{ is_numeric($value) ? number_format($value) : $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
