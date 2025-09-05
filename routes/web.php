<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RagController;
use App\Http\Controllers\RagAnswerController;
use App\Http\Controllers\VertexRagController;
use App\Http\Middleware\ForceJsonForRag;

Route::get('/', fn() => redirect('/front/'));

Route::middleware(['api', ForceJsonForRag::class])->group(function () {
    Route::match(['GET','POST'], '/rag/ping', fn() => response()->json(['ok'=>true,'who'=>'web.bridge.ping']));

    Route::match(['GET','POST'], '/rag/ingest', [RagController::class, 'ingest']);
    Route::match(['GET','POST'], '/rag/query',  [RagController::class, 'query']);
    Route::match(['GET','POST'], '/rag/answer', [RagAnswerController::class, 'answer']);

    // utilitários
    Route::match(['GET','POST'], '/rag/debug/echo', [RagController::class, 'echo']);
    Route::get('/docs/list', [RagController::class, 'listDocs']);
    Route::get('/docs/preview', [RagController::class, 'preview']);

    if (class_exists(VertexRagController::class)) {
        Route::get('/vertex/generate',  [VertexRagController::class, 'generateGet']);
        Route::post('/vertex/generate', [VertexRagController::class, 'generatePost']);
    }

    // utilitários extras
    Route::get('/docs/preview', [RagController::class, 'preview']);
    Route::get('/docs/use', [RagController::class, 'useDoc']);
    Route::get('/docs/clear', [RagController::class, 'clearDoc']);

});
