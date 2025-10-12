<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RagController;
use App\Http\Controllers\RagAnswerController;
use App\Http\Controllers\VertexRagController;
use App\Http\Middleware\ForceJsonForRag;

// As rotas de API (com prefixo /api) já estão sob o grupo 'api' por padrão.
// Envelopamos com ForceJsonForRag para garantir JSON até em erros inesperados.
Route::middleware([ForceJsonForRag::class])->group(function () {

    Route::get('/health', function() { return response()->json(['ok'=>true,'ts'=>now()->toIso8601String()]); });
    Route::match(['GET','POST'], '/rag/ping', function() { return response()->json(['ok'=>true,'who'=>'api.ping']); });

    if (class_exists(VertexRagController::class)) {
        Route::get('/vertex/generate',  [VertexRagController::class, 'generateGet']);
        Route::post('/vertex/generate', [VertexRagController::class, 'generatePost']);
    }

    // Basic RAG Operations
    Route::post('/rag/ingest', [RagController::class, 'ingest']);
    Route::match(['GET','POST'], '/rag/query',  [RagController::class, 'query']);
    Route::match(['GET','POST'], '/rag/answer', [RagAnswerController::class, 'answer']);

    // Enterprise RAG Operations
    Route::post('/rag/generate-answer', [RagController::class, 'generateAnswer']);
    Route::post('/rag/batch-ingest', [RagController::class, 'batchIngest']);
    Route::post('/rag/reprocess-document', [RagController::class, 'reprocessDocument']);

    // Management & Monitoring
    Route::get('/rag/metrics', [RagController::class, 'metrics']);
    Route::get('/rag/cache/stats', [RagController::class, 'cacheStats']);
    Route::post('/rag/cache/clear', [RagController::class, 'clearCache']);
    Route::get('/rag/embeddings/stats', [RagController::class, 'embeddingStats']);

    // Debug & Utilities
    Route::match(['GET','POST'], '/rag/debug/echo', [RagController::class, 'echo']);
    Route::get('/docs/list', [RagController::class, 'listDocs']);
    Route::get('/rag/preview', [RagController::class, 'preview']);
    Route::post('/rag/ingest-quality', [RagController::class, 'ingestWithQuality']);
});

// Python RAG Integration (novo - não mexe no sistema existente)
Route::middleware([ForceJsonForRag::class])->group(function () {
    Route::post('/rag/python-search', [\App\Http\Controllers\RagPythonController::class, 'pythonSearch']);
    Route::get('/rag/python-health', [\App\Http\Controllers\RagPythonController::class, 'pythonHealth']);
    Route::post('/rag/compare-search', [\App\Http\Controllers\RagPythonController::class, 'compareSearch']);
    
    // Feedback System
    Route::post('/rag/feedback', [\App\Http\Controllers\RagFeedbackController::class, 'store']);
    Route::get('/rag/feedback/stats', [\App\Http\Controllers\RagFeedbackController::class, 'stats']);
    Route::get('/rag/feedback/recent', [\App\Http\Controllers\RagFeedbackController::class, 'recent']);
    
    // Bulk Operations & Document Management
    Route::post('/rag/bulk-ingest', [\App\Http\Controllers\BulkIngestController::class, 'bulkIngest']);
    Route::delete('/docs/{id}', [\App\Http\Controllers\DocumentManagerController::class, 'delete']);
    Route::get('/docs/{id}/cache/stats', [\App\Http\Controllers\DocumentManagerController::class, 'cacheStats']);
    Route::delete('/docs/{id}/cache', [\App\Http\Controllers\DocumentManagerController::class, 'clearCache']);
    Route::get('/cache/global-stats', [\App\Http\Controllers\DocumentManagerController::class, 'globalCacheStats']);
});

if (class_exists(\App\Http\Controllers\PdfQualityController::class)) {
    Route::post('/pdf/extract-quality', [\App\Http\Controllers\PdfQualityController::class, 'extractWithQuality']);
}

// API Key Management Routes (protected by auth middleware)
Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
    Route::get('/api-key', [\App\Http\Controllers\ApiKeyController::class, 'show']);
    Route::post('/api-key/generate', [\App\Http\Controllers\ApiKeyController::class, 'generate']);
    Route::post('/api-key/regenerate', [\App\Http\Controllers\ApiKeyController::class, 'regenerate']);
    Route::delete('/api-key/revoke', [\App\Http\Controllers\ApiKeyController::class, 'revoke']);
});

// API Key Authentication Test Route (protected by API key)
Route::middleware([\App\Http\Middleware\ApiKeyAuth::class])->group(function () {
    Route::get('/auth/test', [\App\Http\Controllers\ApiKeyController::class, 'test']);
});

// Excel Structured Query Routes
Route::post('/excel/query', [\App\Http\Controllers\ExcelQueryController::class, 'query']);
Route::get('/excel/{documentId}/structure', [\App\Http\Controllers\ExcelQueryController::class, 'getStructuredData']);

// Video Processing Routes (NEW - 2025-10-12)
Route::post('/video/ingest', [\App\Http\Controllers\VideoController::class, 'ingest']);
Route::post('/video/info', [\App\Http\Controllers\VideoController::class, 'getInfo']);
