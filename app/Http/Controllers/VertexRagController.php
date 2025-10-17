<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\VertexClient;
use Throwable;

class VertexRagController extends Controller
{
    // GET /vertex/generate?q=...
    public function generateGet(Request $req, VertexClient $vertex)
    {
        $q = trim((string)$req->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => false, 'error' => 'Missing query param q'], 422);
        }

        try {
            $text = $vertex->generate($q, []);
            return response()->json(['ok' => true, 'prompt' => $q, 'text' => $text]);
        } catch (Throwable $e) {
            Log::error('vertex.generateGet failed: '.$e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // POST /vertex/generate  body: { "prompt": "...", "contextParts": ["...","..."]? } OR files upload
    public function generatePost(Request $req, VertexClient $vertex)
    {
        // Verificar se é upload de arquivos
        if ($req->hasFile('files')) {
            return $this->generateWithFiles($req, $vertex);
        }

        // Processamento normal (texto apenas)
        $data = $req->validate([
            'prompt'       => 'required|string|min:1',
            'contextParts' => 'sometimes|array',
        ]);

        $prompt = trim($data['prompt']);
        $ctx    = $data['contextParts'] ?? [];

        try {
            $text = $vertex->generate($prompt, $ctx);
            return response()->json(['ok' => true, 'prompt' => $prompt, 'text' => $text]);
        } catch (Throwable $e) {
            Log::error('vertex.generatePost failed: '.$e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // NOVO: Processar upload de arquivos com progresso detalhado
    private function generateWithFiles(Request $req, VertexClient $vertex)
    {
        ini_set('max_execution_time', 300); // 5 minutos para processamento
        set_time_limit(300);

        $startTime = microtime(true);
        $requestId = 'vertex_upload_' . uniqid();

        try {
            Log::info('Vertex file upload started', [
                'request_id' => $requestId,
                'start_time' => $startTime,
                'files_count' => count($req->allFiles()),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // 1. Validar arquivos
            $files = $req->validate([
                'files' => 'required|array|max:5',
                'files.*' => 'file|max:10240', // 10MB por arquivo
                'prompt' => 'required|string|min:1',
                'model' => 'sometimes|string',
                'location' => 'sometimes|string'
            ]);

            $prompt = trim($files['prompt']);
            $model = $files['model'] ?? env('VERTEX_GENERATION_MODEL', 'gemini-1.5-flash');
            $location = $files['location'] ?? env('VERTEX_LOCATION', 'us-central1');

            // 2. Processar cada arquivo
            $processedFiles = [];
            $totalFiles = count($req->file('files'));
            $currentFile = 0;

            foreach ($req->file('files') as $file) {
                $currentFile++;
                $fileName = $file->getClientOriginalName();
                
                Log::info('Processing file', [
                    'request_id' => $requestId,
                    'file_name' => $fileName,
                    'file_index' => $currentFile,
                    'total_files' => $totalFiles,
                    'file_size' => $file->getSize(),
                    'processing_time' => round(microtime(true) - $startTime, 2)
                ]);

                // 3. Extrair conteúdo do arquivo
                $content = $this->extractFileContent($file);
                
                // 4. Gerar resposta com contexto do arquivo
                $fileResponse = $vertex->generate($prompt, [$content]);
                
                $processedFiles[] = [
                    'filename' => $fileName,
                    'size' => $file->getSize(),
                    'content_preview' => mb_substr($content, 0, 200) . '...',
                    'response' => $fileResponse,
                    'processing_time' => round(microtime(true) - $startTime, 2)
                ];
            }

            $totalTime = round(microtime(true) - $startTime, 2);

            Log::info('Vertex file upload completed', [
                'request_id' => $requestId,
                'total_time' => $totalTime,
                'files_processed' => count($processedFiles),
                'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
            ]);

            return response()->json([
                'ok' => true,
                'prompt' => $prompt,
                'model' => $model,
                'location' => $location,
                'files_processed' => count($processedFiles),
                'total_time' => $totalTime,
                'processed_files' => $processedFiles,
                'summary' => "Processados {$totalFiles} arquivo(s) em {$totalTime}s"
            ]);

        } catch (Throwable $e) {
            Log::error('Vertex file upload failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'processing_time' => round(microtime(true) - $startTime, 2)
            ]);
            
            return response()->json([
                'ok' => false, 
                'error' => $e->getMessage(),
                'processing_time' => round(microtime(true) - $startTime, 2)
            ], 500);
        }
    }

    // NOVO: Extrair conteúdo de arquivo
    private function extractFileContent($file)
    {
        $fileName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'txt':
                return file_get_contents($file->getRealPath());
            
            case 'pdf':
                // Para PDF, usar extração simples (sem OCR por enquanto)
                return "Conteúdo PDF: {$fileName} (extração básica)";
            
            case 'docx':
            case 'doc':
                return "Conteúdo Word: {$fileName} (extração básica)";
            
            case 'xlsx':
            case 'xls':
                return "Conteúdo Excel: {$fileName} (extração básica)";
            
            case 'pptx':
            case 'ppt':
                return "Conteúdo PowerPoint: {$fileName} (extração básica)";
            
            default:
                return "Arquivo: {$fileName} (tipo: {$extension})";
        }
    }
}
