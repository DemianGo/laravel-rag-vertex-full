<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column: Form -->
    <div class="space-y-4">
        
        <!-- Document Selector -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">📄 Escolher Documento</label>
            
            <!-- Active Document Badge -->
            <div id="activeDocumentBadge" class="hidden bg-blue-50 border border-blue-200 rounded-md p-3 mb-3">
                <p class="text-sm text-blue-800">
                    <strong>🎯 Documento Ativo:</strong> <span id="activeDocumentTitle">-</span>
                </p>
            </div>
            
            <div class="flex gap-2">
                <select id="pythonDocSelect" 
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base">
                    <option value="">Carregando documentos...</option>
                </select>
                <input id="pythonDocId" type="number" 
                       class="hidden flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="Ou digite ID manual">
                <button id="toggleDocInput" type="button" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        title="Alternar entre select e input manual">
                    🔢
                </button>
                <button id="btnShowTranscription" type="button" 
                        class="hidden inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        title="Ver transcrição completa (apenas para vídeos)">
                    📄 Ver Transcrição
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Selecione um documento da lista ou digite o ID manualmente</p>
        </div>

        <!-- Suggested Questions -->
        <div id="pythonSuggestedQuestions" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">💡 Perguntas Sugeridas:</label>
            <div id="pythonSuggestedList" class="flex flex-wrap gap-2"></div>
        </div>

        <!-- Query Input -->
        <div>
            <label for="pythonQuery" class="block text-sm font-medium text-gray-700 mb-2">💬 Sua Pergunta</label>
            <textarea id="pythonQuery" rows="3" 
                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Digite sua pergunta aqui... Exemplo: 'Quais os certificados?'"></textarea>
        </div>

        <!-- Smart Mode Checkbox -->
        <div class="flex items-start">
            <div class="flex items-center h-5">
                <input id="pythonUseSmartMode" type="checkbox" checked
                       class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
            </div>
            <div class="ml-3 text-sm">
                <label for="pythonUseSmartMode" class="font-medium text-gray-700">🧠 Modo Inteligente (Recomendado)</label>
                <p class="text-gray-500">Sistema decide automaticamente a melhor estratégia</p>
            </div>
        </div>

        <!-- Search Button -->
        <div>
            <button id="pythonSearchBtn" type="button"
                    class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                🔍 Buscar
            </button>
        </div>

        <!-- Advanced Options Toggle -->
        <div>
            <button type="button" 
                    onclick="document.getElementById('pythonAdvancedOptions').classList.toggle('hidden')"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                ⚙️ Opções Avançadas (opcional)
            </button>
        </div>

        <!-- Advanced Options (Collapsible) -->
        <div id="pythonAdvancedOptions" class="hidden bg-gray-50 rounded-lg p-4 space-y-4">
            <h4 class="text-sm font-medium text-gray-700">Controles Técnicos</h4>
            
            <!-- Top-K and Threshold -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="pythonTopK" class="block text-xs font-medium text-gray-700">Top-K</label>
                    <input id="pythonTopK" type="number" value="5" min="1" max="20"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="pythonThreshold" class="block text-xs font-medium text-gray-700">Threshold</label>
                    <input id="pythonThreshold" type="number" value="0.3" min="0.05" max="1.0" step="0.05"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>

            <!-- Checkboxes -->
            <div class="space-y-2">
                <div class="flex items-center">
                    <input id="pythonIncludeAnswer" type="checkbox" checked
                           class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <label for="pythonIncludeAnswer" class="ml-2 block text-xs text-gray-700">Incluir resposta LLM</label>
                </div>
                <div class="flex items-center">
                    <input id="pythonUseFullDocument" type="checkbox"
                           class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <label for="pythonUseFullDocument" class="ml-2 block text-xs text-gray-700">Usar Documento Completo</label>
                </div>
            </div>

            <!-- Strictness -->
            <div>
                <label for="pythonStrictness" class="block text-xs font-medium text-gray-700">Strictness (0-3)</label>
                <select id="pythonStrictness"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="0">0 - Muito Permissivo</option>
                    <option value="1">1 - Permissivo</option>
                    <option value="2" selected>2 - Moderado (Padrão)</option>
                    <option value="3">3 - Rigoroso (Sem LLM)</option>
                </select>
            </div>

            <!-- Mode and Format -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="pythonMode" class="block text-xs font-medium text-gray-700">Modo</label>
                    <select id="pythonMode"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="auto" selected>Auto (Detecta)</option>
                        <option value="direct">Direct</option>
                        <option value="summary">Summary</option>
                        <option value="quote">Quote</option>
                        <option value="list">List</option>
                        <option value="table">Table</option>
                        <option value="document_full">Document Full</option>
                    </select>
                </div>
                <div>
                    <label for="pythonFormat" class="block text-xs font-medium text-gray-700">Formato</label>
                    <select id="pythonFormat"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="plain" selected>Plain</option>
                        <option value="markdown">Markdown</option>
                        <option value="html">HTML</option>
                    </select>
                </div>
            </div>

            <!-- Length and Citations -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="pythonLength" class="block text-xs font-medium text-gray-700">Comprimento</label>
                    <select id="pythonLength"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="auto" selected>Auto</option>
                        <option value="short">Short</option>
                        <option value="medium">Medium</option>
                        <option value="long">Long</option>
                        <option value="xl">XL</option>
                    </select>
                </div>
                <div>
                    <label for="pythonCitations" class="block text-xs font-medium text-gray-700">Citações (0-10)</label>
                    <input id="pythonCitations" type="number" value="0" min="0" max="10"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>

            <!-- Additional Buttons -->
            <div class="flex gap-2">
                <button id="pythonCompareBtn" type="button"
                        class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    ⚖️ Comparar PHP vs Python
                </button>
                <button id="pythonHealthBtn" type="button"
                        class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    🏥 Health Check
                </button>
            </div>
        </div>

    </div>

    <!-- Right Column: Results -->
    <div class="space-y-4">
        
        <!-- Status -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Resultados Python RAG</label>
            <div id="pythonStatus" class="bg-blue-50 border border-blue-200 rounded-md p-3 text-sm text-blue-800">
                Pronto para busca...
            </div>
        </div>

        <!-- Chunks -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chunks Encontrados</label>
            <div id="pythonChunks" class="bg-gray-50 border border-gray-200 rounded-md p-3 max-h-72 overflow-y-auto text-sm"></div>
        </div>

        <!-- Answer -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Resposta LLM</label>
            <div id="pythonAnswer" class="bg-gray-50 border border-gray-200 rounded-md p-3 max-h-48 overflow-y-auto text-sm"></div>
            
            <!-- Feedback Buttons -->
            <div id="feedbackSection" class="hidden mt-3 flex items-center gap-2">
                <span class="text-xs text-gray-500">Esta resposta foi útil?</span>
                <button id="feedbackThumbsUp" type="button"
                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                        title="Resposta útil">
                    👍 Útil
                </button>
                <button id="feedbackThumbsDown" type="button"
                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        title="Resposta não útil">
                    👎 Não útil
                </button>
                <span id="feedbackStatus" class="hidden text-xs text-green-600"></span>
            </div>
        </div>

        <!-- Metadata -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Metadados</label>
            <pre id="pythonMetadata" class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-auto max-h-48"></pre>
        </div>

    </div>
</div>


