<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\DocumentCacheService;

class DocumentManagerController extends Controller
{
    private DocumentCacheService $cacheService;
    
    public function __construct()
    {
        $this->cacheService = new DocumentCacheService();
    }
    
    /**
     * Delete a document and all its related data
     * DELETE /api/docs/{id}
     */
    public function delete(int $id)
    {
        try {
            $doc = DB::table('documents')->where('id', $id)->first();
            
            if (!$doc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Documento não encontrado'
                ], 404);
            }
            
            // Get metadata to find file path
            $metadata = json_decode($doc->metadata ?? '{}', true);
            $filePath = $metadata['file_path'] ?? null;
            
            // Delete chunks
            $chunksDeleted = DB::table('chunks')->where('document_id', $id)->delete();
            
            // Delete document
            DB::table('documents')->where('id', $id)->delete();
            
            // Clear cache
            $this->cacheService->clearDocumentCache($id);
            
            // Delete physical file if exists
            $fileDeleted = false;
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
                $fileDeleted = true;
            }
            
            Log::info('Document deleted', [
                'document_id' => $id,
                'title' => $doc->title,
                'chunks_deleted' => $chunksDeleted,
                'file_deleted' => $fileDeleted
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Documento excluído com sucesso',
                'document_id' => $id,
                'title' => $doc->title,
                'chunks_deleted' => $chunksDeleted,
                'file_deleted' => $fileDeleted,
                'cache_cleared' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Document deletion failed', [
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir documento: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get cache statistics for a specific document
     * GET /api/docs/{id}/cache/stats
     */
    public function cacheStats(int $id)
    {
        try {
            $doc = DB::table('documents')->where('id', $id)->first();
            
            if (!$doc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Documento não encontrado'
                ], 404);
            }
            
            $stats = $this->cacheService->getDocumentCacheStats($id);
            $stats['document_title'] = $doc->title;
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clear cache for a specific document
     * DELETE /api/docs/{id}/cache
     */
    public function clearCache(int $id)
    {
        try {
            $doc = DB::table('documents')->where('id', $id)->first();
            
            if (!$doc) {
                return response()->json([
                    'success' => false,
                    'error' => 'Documento não encontrado'
                ], 404);
            }
            
            $cleared = $this->cacheService->clearDocumentCache($id);
            
            return response()->json([
                'success' => $cleared,
                'message' => $cleared 
                    ? 'Cache do documento limpo com sucesso' 
                    : 'Falha ao limpar cache',
                'document_id' => $id,
                'document_title' => $doc->title
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get global cache statistics across all documents
     * GET /api/cache/global-stats
     */
    public function globalCacheStats()
    {
        try {
            $stats = $this->cacheService->getGlobalCacheStats();
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

