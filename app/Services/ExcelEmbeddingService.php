<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Chunk;
use Illuminate\Support\Facades\Log;

/**
 * Serviço NOVO para geração OPCIONAL de embeddings em XLSX
 * NÃO afeta outros tipos de arquivo
 * Criado: 2025-10-14
 */
class ExcelEmbeddingService
{
    private $pythonScript;
    
    public function __construct()
    {
        $this->pythonScript = base_path('scripts/rag_search/batch_embeddings.py');
    }
    
    /**
     * Verifica se documento é XLSX e precisa de embeddings
     */
    public function needsEmbeddings(Document $document): bool
    {
        // Só para XLSX
        if (!$this->isExcelDocument($document)) {
            return false;
        }
        
        // Verifica se já tem embeddings
        $hasEmbeddings = Chunk::where('document_id', $document->id)
            ->whereNotNull('embedding')
            ->exists();
        
        return !$hasEmbeddings;
    }
    
    /**
     * Verifica se documento é Excel
     */
    private function isExcelDocument(Document $document): bool
    {
        $title = strtolower($document->title ?? '');
        return str_ends_with($title, '.xlsx') || str_ends_with($title, '.xls');
    }
    
    /**
     * Gera embeddings para documento XLSX
     * 
     * @param int $documentId
     * @param bool $async Se true, executa em background
     * @return array ['success' => bool, 'message' => string, 'chunks_processed' => int]
     */
    public function generateEmbeddings(int $documentId, bool $async = false): array
    {
        $document = Document::find($documentId);
        
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Documento não encontrado',
                'chunks_processed' => 0
            ];
        }
        
        // Verifica se é XLSX
        if (!$this->isExcelDocument($document)) {
            return [
                'success' => false,
                'message' => 'Documento não é XLSX',
                'chunks_processed' => 0
            ];
        }
        
        $chunksCount = Chunk::where('document_id', $documentId)->count();
        
        if ($chunksCount === 0) {
            return [
                'success' => false,
                'message' => 'Nenhum chunk encontrado',
                'chunks_processed' => 0
            ];
        }
        
        Log::info('Iniciando geração de embeddings para XLSX', [
            'document_id' => $documentId,
            'chunks_count' => $chunksCount,
            'async' => $async
        ]);
        
        if ($async) {
            // Executa em background (não bloqueia)
            return $this->generateAsync($documentId, $chunksCount);
        } else {
            // Executa síncronamente (bloqueia até terminar)
            return $this->generateSync($documentId, $chunksCount);
        }
    }
    
    /**
     * Geração síncrona (bloqueia)
     */
    private function generateSync(int $documentId, int $chunksCount): array
    {
        $startTime = microtime(true);
        
        $command = sprintf(
            'python3 %s --document-id %d 2>&1',
            escapeshellarg($this->pythonScript),
            $documentId
        );
        
        exec($command, $output, $returnCode);
        
        $executionTime = microtime(true) - $startTime;
        
        if ($returnCode === 0) {
            Log::info('Embeddings gerados com sucesso', [
                'document_id' => $documentId,
                'chunks_processed' => $chunksCount,
                'execution_time' => round($executionTime, 2)
            ]);
            
            return [
                'success' => true,
                'message' => "Embeddings gerados: {$chunksCount} chunks em " . round($executionTime, 1) . "s",
                'chunks_processed' => $chunksCount,
                'execution_time' => round($executionTime, 2)
            ];
        } else {
            Log::error('Erro ao gerar embeddings', [
                'document_id' => $documentId,
                'return_code' => $returnCode,
                'output' => implode("\n", $output)
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro ao gerar embeddings',
                'chunks_processed' => 0,
                'error' => implode("\n", $output)
            ];
        }
    }
    
    /**
     * Geração assíncrona (background)
     */
    private function generateAsync(int $documentId, int $chunksCount): array
    {
        $command = sprintf(
            'nohup python3 %s --document-id %d > /dev/null 2>&1 &',
            escapeshellarg($this->pythonScript),
            $documentId
        );
        
        exec($command);
        
        Log::info('Geração de embeddings iniciada em background', [
            'document_id' => $documentId,
            'chunks_count' => $chunksCount
        ]);
        
        return [
            'success' => true,
            'message' => "Geração iniciada em background: {$chunksCount} chunks (levará ~" . $this->estimateTime($chunksCount) . ")",
            'chunks_processed' => 0,
            'async' => true,
            'estimated_time' => $this->estimateTime($chunksCount)
        ];
    }
    
    /**
     * Estima tempo de processamento (baseado em performance real)
     */
    private function estimateTime(int $chunksCount): string
    {
        // Performance real observada:
        // - Chunks pequenos (< 100): ~0.1s por chunk
        // - Chunks médios (100-500): ~0.05s por chunk  
        // - Chunks grandes (500+): ~0.02s por chunk (batch processing)
        
        if ($chunksCount < 100) {
            $seconds = max(1, round($chunksCount * 0.1));
        } elseif ($chunksCount < 500) {
            $seconds = max(2, round($chunksCount * 0.05));
        } else {
            // Para arquivos grandes, batch processing é mais eficiente
            $seconds = max(3, round($chunksCount * 0.02));
        }
        
        // Garantir estimativa mínima realista
        $seconds = max($seconds, 2);
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = round($seconds / 60);
            return "{$minutes} min";
        } else {
            $hours = round($seconds / 3600, 1);
            return "{$hours}h";
        }
    }
    
    /**
     * Verifica status da geração de embeddings
     */
    public function getEmbeddingStatus(int $documentId): array
    {
        $totalChunks = Chunk::where('document_id', $documentId)->count();
        $chunksWithEmbeddings = Chunk::where('document_id', $documentId)
            ->whereNotNull('embedding')
            ->count();
        
        $percentage = $totalChunks > 0 ? round(($chunksWithEmbeddings / $totalChunks) * 100, 1) : 0;
        
        return [
            'total_chunks' => $totalChunks,
            'chunks_with_embeddings' => $chunksWithEmbeddings,
            'percentage' => $percentage,
            'completed' => $percentage === 100.0,
            'in_progress' => $percentage > 0 && $percentage < 100
        ];
    }
}

