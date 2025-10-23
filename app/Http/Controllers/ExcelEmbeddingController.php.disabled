<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExcelEmbeddingService;
use Illuminate\Support\Facades\Validator;

/**
 * Controller NOVO para geração opcional de embeddings em XLSX
 * NÃO afeta uploads existentes ou outros controllers
 * Criado: 2025-10-14
 */
class ExcelEmbeddingController extends Controller
{
    private $embeddingService;
    
    public function __construct(ExcelEmbeddingService $service)
    {
        $this->embeddingService = $service;
    }
    
    /**
     * Gera embeddings para documento XLSX
     * 
     * POST /api/excel/generate-embeddings
     * 
     * Body: {
     *   "document_id": 282,
     *   "async": true  // opcional, default: true
     * }
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer|exists:documents,id',
            'async' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validação falhou',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $documentId = $request->input('document_id');
        $async = $request->input('async', true); // Default: assíncrono
        
        $result = $this->embeddingService->generateEmbeddings($documentId, $async);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Verifica status da geração de embeddings
     * 
     * GET /api/excel/{documentId}/embeddings-status
     */
    public function status(int $documentId)
    {
        $status = $this->embeddingService->getEmbeddingStatus($documentId);
        
        return response()->json([
            'success' => true,
            'document_id' => $documentId,
            'status' => $status
        ]);
    }
}

