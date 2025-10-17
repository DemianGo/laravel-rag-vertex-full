<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UniversalEmbeddingService;
use Illuminate\Support\Facades\Validator;

/**
 * Controller UNIVERSAL para geração opcional de embeddings em QUALQUER tipo de arquivo
 * NÃO afeta outros controllers existentes
 * Criado: 2025-10-14
 */
class UniversalEmbeddingController extends Controller
{
    private $embeddingService;
    
    public function __construct(UniversalEmbeddingService $service)
    {
        $this->embeddingService = $service;
    }
    
    /**
     * Gera embeddings para QUALQUER documento
     * 
     * POST /api/embeddings/generate
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
     * GET /api/embeddings/{documentId}/status
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
    
    /**
     * Retorna informações sobre o tipo de arquivo
     * 
     * GET /api/embeddings/file-info?filename=arquivo.pdf
     */
    public function fileInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validação falhou',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $fileName = $request->input('filename');
        $fileInfo = $this->embeddingService->getFileTypeInfo($fileName);
        
        return response()->json([
            'success' => true,
            'filename' => $fileName,
            'file_info' => $fileInfo
        ]);
    }
}


