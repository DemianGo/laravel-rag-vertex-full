<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RagController;
use App\Http\Controllers\RagAnswerController;
use App\Http\Controllers\VertexRagController;
use App\Http\Middleware\ForceJsonForRag;

// As rotas de API (com prefixo /api) já estão sob o grupo 'api' por padrão.
// Envelopamos com ForceJsonForRag para garantir JSON até em erros inesperados.
Route::middleware([ForceJsonForRag::class])->group(function () {

    Route::get('/health', fn() => response()->json(['ok'=>true,'ts'=>now()->toIso8601String()]));
    Route::match(['GET','POST'], '/rag/ping', fn() => response()->json(['ok'=>true,'who'=>'api.ping']));

    if (class_exists(VertexRagController::class)) {
        Route::get('/vertex/generate',  [VertexRagController::class, 'generateGet']);
        Route::post('/vertex/generate', [VertexRagController::class, 'generatePost']);
    }

    Route::post('/rag/ingest', [RagController::class, 'ingest']);
    Route::match(['GET','POST'], '/rag/query',  [RagController::class, 'query']);
    Route::match(['GET','POST'], '/rag/answer', [RagAnswerController::class, 'answer']);

    Route::match(['GET','POST'], '/rag/debug/echo', [RagController::class, 'echo']);
    Route::get('/docs/list', [RagController::class, 'listDocs']);
});
