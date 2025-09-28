<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Chat') }}
        </h2>
    </x-slot>
<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">RAG Chat Assistant</h1>
                        <p class="text-sm text-gray-600">Ask questions about your documents using natural language</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Remaining Tokens</div>
                        <div class="text-lg font-semibold text-blue-600">
                            {{ number_format($user->tokens_limit - $user->tokens_used) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan Features -->
            <div class="px-4 py-3 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->plan === 'enterprise' ? 'bg-purple-100 text-purple-800' : ($user->plan === 'pro' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ ucfirst($user->plan) }} Plan
                        </span>
                        <span class="text-sm text-gray-500">
                            @if($user->plan === 'free')
                                Basic queries only
                            @elseif($user->plan === 'pro')
                                Basic + Advanced queries
                            @else
                                All features unlocked
                            @endif
                        </span>
                    </div>
                    @if($user->plan === 'free')
                    <a href="{{ route('plans.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                        Upgrade for advanced features →
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Chat Interface -->
        <div x-data="ragChat()" class="bg-white shadow rounded-lg">
            <!-- Messages Area -->
            <div class="h-96 overflow-y-auto p-4 space-y-4" x-ref="messagesContainer">
                <!-- Welcome Message -->
                <div x-show="messages.length === 0" class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Start a conversation</h3>
                    <p class="mt-1 text-sm text-gray-500">Ask me anything about your uploaded documents.</p>
                </div>

                <!-- Messages -->
                <template x-for="message in messages" :key="message.id">
                    <div class="flex" :class="message.type === 'user' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg"
                             :class="message.type === 'user'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-900'">
                            <p class="text-sm" x-text="message.content"></p>
                            <div class="mt-1 text-xs opacity-75" x-text="message.timestamp"></div>

                            <!-- Sources for assistant messages -->
                            <div x-show="message.type === 'assistant' && message.sources && message.sources.length > 0" class="mt-2">
                                <div class="text-xs opacity-75 mb-1">Sources:</div>
                                <template x-for="source in message.sources.slice(0, 3)">
                                    <div class="text-xs bg-white bg-opacity-20 rounded px-2 py-1 mb-1">
                                        <span x-text="'Doc ' + source.document_id + ' - ' + source.content.substring(0, 50) + '...'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Loading indicator -->
                <div x-show="loading" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg px-4 py-2">
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-600"></div>
                            <span class="text-sm text-gray-600">Thinking...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t border-gray-200 p-4">
                <!-- Query Info & Plan Features -->
                <div class="mb-3 bg-gray-50 p-3 rounded-lg">
                    <div class="text-sm text-gray-700">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">🚀 Enterprise RAG System</span>
                            <span class="text-xs bg-{{ $user->plan === 'enterprise' ? 'purple' : ($user->plan === 'pro' ? 'blue' : 'gray') }}-100 text-{{ $user->plan === 'enterprise' ? 'purple' : ($user->plan === 'pro' ? 'blue' : 'gray') }}-800 px-2 py-1 rounded">{{ ucfirst($user->plan) }}</span>
                        </div>

                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                            @if($user->plan === 'enterprise')
                                <div class="flex items-center"><span class="text-green-500">✓</span> Geração Avançada</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> Reranking Semântico</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> Citações Precisas</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> 15 Resultados</div>
                            @elseif($user->plan === 'pro')
                                <div class="flex items-center"><span class="text-green-500">✓</span> Geração Avançada</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> Reranking</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> Citações</div>
                                <div class="flex items-center"><span class="text-green-500">✓</span> 10 Resultados</div>
                            @else
                                <div class="flex items-center"><span class="text-gray-400">○</span> Busca Básica</div>
                                <div class="flex items-center"><span class="text-gray-400">○</span> 5 Resultados</div>
                                <div class="flex items-center text-blue-600"><a href="{{ route('plans.index') }}">Upgrade para mais recursos →</a></div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Input Form -->
                <form @submit.prevent="sendMessage()" class="flex space-x-2">
                    <input type="text" x-model="currentMessage" :disabled="loading"
                           placeholder="Ask a question about your documents..."
                           class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" :disabled="loading || !currentMessage.trim()"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!loading">Send</span>
                        <span x-show="loading">Sending...</span>
                    </button>
                </form>

                <!-- Usage Notice -->
                <div class="mt-2 text-xs text-gray-500">
                    Using {{ $user->tokens_used }}/{{ number_format($user->tokens_limit) }} tokens this month
                    @if(($user->tokens_used / $user->tokens_limit) > 0.8)
                        <span class="text-orange-600 font-medium">- Running low!</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

        </div>
    </div>

    <script>
    function ragChat() {
        return {
            messages: [],
            currentMessage: '',
            loading: false,
            messageId: 1,

            sendMessage() {
                if (!this.currentMessage.trim() || this.loading) return;

                const userMessage = {
                    id: this.messageId++,
                    type: 'user',
                    content: this.currentMessage,
                    timestamp: new Date().toLocaleTimeString()
                };

                this.messages.push(userMessage);
                const query = this.currentMessage;
                this.currentMessage = '';
                this.loading = true;

                this.scrollToBottom();

                // Send to API
                fetch('{{ route("chat.query") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        query: query
                    })
                })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;

                    if (data.success || data.answer) {
                        let content = data.answer || 'No response received';

                        // Add enhanced info for enterprise responses
                        if (data.method || data.type || data.plan_features) {
                            content += '\n\n' + '─'.repeat(40);

                            if (data.type) content += '\n🤖 Método: ' + (data.type === 'generated' ? 'Geração Avançada' : 'Busca Semântica');
                            if (data.method) content += '\n🔍 API: ' + data.method;
                            if (data.results_count) content += '\n📊 Resultados: ' + data.results_count + ' encontrados';
                            if (data.confidence) content += '\n🎯 Confiança: ' + Math.round(data.confidence * 100) + '%';

                            // Plan features indicator
                            if (data.plan_features) {
                                let features = [];
                                if (data.plan_features.advanced_generation) features.push('Geração Avançada');
                                if (data.plan_features.reranking) features.push('Reranking');
                                if (data.plan_features.citations) features.push('Citações');
                                if (features.length > 0) {
                                    content += '\n✨ Recursos: ' + features.join(', ');
                                }
                            }
                        }

                        // Add suggestions if available
                        if (data.suggestions && data.suggestions.length > 0) {
                            content += '\n\n💡 **Sugestões:**';
                            data.suggestions.forEach((suggestion, i) => {
                                content += `\n${i + 1}. ${suggestion}`;
                            });
                        }

                        // Add document info if search failed
                        if (data.document_info) {
                            content += '\n\n📄 **Informações do documento:**';
                            if (data.document_info.title) content += '\n• Título: ' + data.document_info.title;
                            if (data.document_info.word_count) content += '\n• Palavras: ' + data.document_info.word_count;
                            if (data.document_info.keywords_found) content += '\n• Palavras-chave: ' + data.document_info.keywords_found;
                        }

                        const assistantMessage = {
                            id: this.messageId++,
                            type: 'assistant',
                            content: content,
                            timestamp: new Date().toLocaleTimeString(),
                            sources: data.sources || [],
                            confidence: data.confidence,
                            enterprise: true
                        };
                        this.messages.push(assistantMessage);
                    } else {
                        const errorMessage = {
                            id: this.messageId++,
                            type: 'assistant',
                            content: data.error || 'Sorry, something went wrong. Please try again.',
                            timestamp: new Date().toLocaleTimeString(),
                            sources: []
                        };
                        this.messages.push(errorMessage);

                        // Show upgrade prompt if needed
                        if (data.upgrade_url) {
                            setTimeout(() => {
                                if (confirm('Would you like to upgrade your plan for more features?')) {
                                    window.location.href = data.upgrade_url;
                                }
                            }, 1000);
                        }
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error:', error);

                    const errorMessage = {
                        id: this.messageId++,
                        type: 'assistant',
                        content: 'Sorry, there was a network error. Please check your connection and try again.',
                        timestamp: new Date().toLocaleTimeString(),
                        sources: []
                    };
                    this.messages.push(errorMessage);
                })
                .finally(() => {
                    this.scrollToBottom();
                });
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight;
                });
            }
        }
    }
    </script>
</x-app-layout>