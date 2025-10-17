<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column: Actions -->
    <div class="space-y-6">
        
        <!-- System Metrics -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Métricas do Sistema</h3>
            <div class="space-y-2">
                <button id="btnMetrics" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Carregar Métricas
                </button>
                <button id="btnCacheStats" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Estatísticas de Cache
                </button>
                <button id="btnEmbeddingsStats" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Estatísticas de Embeddings
                </button>
            </div>
        </div>

        <!-- Feedback & Analytics -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Feedback & Analytics</h3>
            <div class="space-y-2">
                <button id="btnFeedbackStats" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    📈 Estatísticas de Feedback
                </button>
                <button id="btnRecentFeedbacks" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    📝 Feedbacks Recentes
                </button>
            </div>
        </div>

        <!-- Cache Management -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Cache Management</h3>
            <div class="space-y-2">
                <button id="btnClearCache" type="button"
                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                    Limpar Cache
                </button>
            </div>
        </div>

    </div>

    <!-- Right Column: Results -->
    <div>
        <h3 class="text-lg font-medium text-gray-900 mb-3">Resultado</h3>
        <pre id="metricsOut" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-xs font-mono overflow-auto min-h-[400px] max-h-[600px]">Clique em um botão para carregar métricas...</pre>
    </div>
</div>


