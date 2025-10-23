<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upload Rápido (Sistema Bypass)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <!-- Alert Messages -->
                    @if (session('success'))
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            <strong>✅ Sucesso:</strong> {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <strong>❌ Erro:</strong> {{ session('error') }}
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="mb-4 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded">
                            <strong>ℹ️ Info:</strong> {{ session('info') }}
                        </div>
                    @endif

                    <!-- Info Box -->
                    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h3 class="text-lg font-semibold text-yellow-800 mb-2">🚀 Sistema Upload Bypass</h3>
                        <p class="text-yellow-700 mb-2">
                            <strong>Vantagens:</strong> Upload garantido em menos de 5 segundos, sem dependências complexas.
                        </p>
                        <p class="text-yellow-700 mb-2">
                            <strong>Funcionalidade:</strong> Processa o arquivo, cria chunks básicos e permite uso imediato no chat.
                        </p>
                        <p class="text-yellow-700">
                            <strong>Formatos suportados:</strong> PDF, TXT, HTML, CSV, JSON, DOC/DOCX
                        </p>
                    </div>

                    <!-- Upload Form -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-semibold mb-4">📤 Upload de Documento</h3>

                        <form action="{{ route('upload-bypass.upload') }}" method="POST" enctype="multipart/form-data"
                              class="space-y-4">
                            @csrf

                            <div>
                                <label for="document" class="block text-sm font-medium text-gray-700 mb-1">
                                    Selecionar Arquivo
                                </label>
                                <input type="file"
                                       id="document"
                                       name="document"
                                       required
                                       accept=".pdf,.txt,.html,.csv,.json,.doc,.docx"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                                              focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="mt-1 text-sm text-gray-500">
                                    Máximo 50MB. Formatos: PDF, TXT, HTML, CSV, JSON, DOC, DOCX
                                </p>
                            </div>

                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                                    Título do Documento (Opcional)
                                </label>
                                <input type="text"
                                       id="title"
                                       name="title"
                                       maxlength="255"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                                              focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="mt-1 text-sm text-gray-500">
                                    Se não informado, usará o nome do arquivo
                                </p>
                            </div>

                            <div>
                                <button type="submit"
                                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md
                                               hover:bg-green-700 focus:outline-none focus:ring-2
                                               focus:ring-green-500 focus:ring-offset-2 font-semibold">
                                    🚀 Upload Rápido (< 5 segundos)
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Features Comparison -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4">📊 Comparação de Sistemas</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-900">Feature</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-green-600">Upload Bypass</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-blue-600">Upload Original</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Tempo de Upload</td>
                                        <td class="px-4 py-2 text-green-600">< 5 segundos ✅</td>
                                        <td class="px-4 py-2 text-red-600">Até 77 segundos ❌</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Garantia de Funcionamento</td>
                                        <td class="px-4 py-2 text-green-600">100% ✅</td>
                                        <td class="px-4 py-2 text-yellow-600">Depende de serviços ⚠️</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Chunks Criados</td>
                                        <td class="px-4 py-2 text-green-600">Sempre > 0 ✅</td>
                                        <td class="px-4 py-2 text-red-600">Pode ser 0 ❌</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Uso no Chat</td>
                                        <td class="px-4 py-2 text-green-600">Imediato ✅</td>
                                        <td class="px-4 py-2 text-green-600">Imediato ✅</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Embeddings</td>
                                        <td class="px-4 py-2 text-yellow-600">Não (busca por texto) ⚠️</td>
                                        <td class="px-4 py-2 text-green-600">Sim (busca semântica) ✅</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium">Processamento Avançado</td>
                                        <td class="px-4 py-2 text-blue-600">Opcional posteriormente 🔄</td>
                                        <td class="px-4 py-2 text-green-600">Automático ✅</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Navigation Links -->
                    <div class="mt-6 flex space-x-4">
                        <a href="{{ route('documents.index') }}"
                           class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                            📂 Ver Todos Documentos
                        </a>
                        <a href="{{ route('chat.index') }}"
                           class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700">
                            💬 Ir para Chat
                        </a>
                        <a href="{{ route('dashboard') }}"
                           class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700">
                            🏠 Dashboard
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Upload Progress (Optional Enhancement) -->
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = document.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;

            button.innerHTML = '⏳ Processando...';
            button.disabled = true;

            // Re-enable after 10 seconds as safety measure
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 10000);
        });
    </script>
</x-app-layout>