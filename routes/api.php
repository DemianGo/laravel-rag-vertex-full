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
    Route::post('/rag/ingest-quality', [RagController::class, 'ingestWithQuality']);
});
if (class_exists(\App\Http\Controllers\PdfQualityController::class)) {
    Route::post('/pdf/extract-quality', [\App\Http\Controllers\PdfQualityController::class, 'extractWithQuality']);
}
