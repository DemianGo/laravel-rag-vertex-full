<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column: Form -->
    <div class="space-y-4">
        
        <!-- Source URL -->
        <div>
            <label for="ingUrl" class="block text-sm font-medium text-gray-700">Fonte (URL opcional)</label>
            <input type="text" id="ingUrl" 
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                   placeholder="https://exemplo.com/artigo (opcional)">
        </div>

        <!-- Video URL -->
        <div>
            <label for="ingVideoUrl" class="block text-sm font-medium text-gray-700">
                🎬 URL de Vídeo 
                <span class="text-xs text-gray-500">• YouTube, Vimeo, etc</span>
            </label>
            <input type="text" id="ingVideoUrl" 
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                   placeholder="https://youtube.com/watch?v=... (opcional)">
            <p class="mt-1 text-xs text-gray-500">
                Suporta: YouTube, Vimeo, Dailymotion, Facebook, Instagram, TikTok e 1000+ sites
            </p>
        </div>

        <!-- Text Input -->
        <div>
            <label for="ingText" class="block text-sm font-medium text-gray-700">Texto</label>
            <textarea id="ingText" rows="6" 
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Cole aqui um texto para ingestão"></textarea>
        </div>

        <!-- File Upload (Dropzone) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Arquivos (PDF/Doc/etc.) 
                <span class="text-xs text-gray-500">• Máx: 5 arquivos, 500MB, 5.000 páginas cada</span>
            </label>
            <div id="dzIngest" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors cursor-pointer">
                <div class="text-4xl mb-2">📄</div>
                <div class="text-sm text-gray-600">
                    Arraste e solte arquivos aqui ou 
                    <label class="text-indigo-600 hover:text-indigo-500 underline cursor-pointer">
                        <input id="ingFiles" type="file" multiple class="hidden">
                        clique para selecionar
                    </label>
                </div>
                <div id="ingFileList" class="mt-3 space-y-2"></div>
            </div>
            <!-- File validation info (populated by file-validator.js) -->
            <div id="fileValidationInfo" class="mt-2"></div>
        </div>

        <!-- Metadata -->
        <div>
            <label for="ingMeta" class="block text-sm font-medium text-gray-700">Metadata (JSON opcional)</label>
            <textarea id="ingMeta" rows="3" 
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono text-xs"
                      placeholder='{"source":"manual","tags":["demo"]}'></textarea>
        </div>

        <!-- Submit Button -->
        <div>
            <button id="btnIngest" 
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Enviar Ingestão
            </button>
        </div>

        <!-- Progress Bar -->
        <div id="ingProgressWrap" class="hidden">
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div id="ingProgress" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <p id="ingProgressText" class="mt-1 text-xs text-gray-500"></p>
        </div>

    </div>

    <!-- Right Column: Response -->
    <div>
        <h3 class="text-lg font-medium text-gray-900 mb-3">Resposta</h3>
        <pre id="ingestOut" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-xs font-mono overflow-auto max-h-[600px]"></pre>
    </div>
</div>

<style>
    /* Dropzone dragover effect */
    #dzIngest.dragover {
        background-color: #eef2ff;
        border-color: #6366f1;
    }
    
    /* File chips */
    .file-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 9999px;
        background-color: white;
        font-size: 0.875rem;
    }
    
    .file-chip .x {
        cursor: pointer;
        font-weight: bold;
        color: #dc2626;
    }
</style>


