<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Services\DocumentPageValidator;

class BulkIngestController extends Controller
{
    private const MAX_SIMULTANEOUS_FILES = 5;
    private const MAX_FILE_SIZE = 500 * 1024 * 1024; // 500MB

    /**
     * Upload and ingest multiple files simultaneously
     * POST /api/rag/bulk-ingest
     */
    public function bulkIngest(Request $req)
    {
        // Increase limits for bulk processing
        ini_set('max_execution_time', 600); // 10 minutes for bulk
        ini_set('memory_limit', '4G'); // 4GB for multiple files
        set_time_limit(600);

        $requestId = 'bulk_' . uniqid();
        $startTime = microtime(true);
        
        Log::info('Bulk ingest started', [
            'request_id' => $requestId,
            'files_count' => count($req->allFiles()),
        ]);

        // Get all uploaded files
        $files = [];
        if ($req->has('files')) {
            $files = $req->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }
        }

        if (empty($files)) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhum arquivo enviado. Use o campo "files[]" para múltiplos arquivos.'
            ], 422);
        }

        if (count($files) > self::MAX_SIMULTANEOUS_FILES) {
            return response()->json([
                'success' => false,
                'error' => 'Limite de ' . self::MAX_SIMULTANEOUS_FILES . ' arquivos simultâneos excedido.',
                'files_sent' => count($files),
                'max_allowed' => self::MAX_SIMULTANEOUS_FILES
            ], 422);
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        // Get tenant_slug from authenticated user
        $user = auth('sanctum')->user();
        $userId = $user ? $user->id : $req->input('user_id', 1);
        $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');

        foreach ($files as $index => $file) {
            if (!($file instanceof UploadedFile)) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'error' => 'Arquivo inválido'
                ];
                $failCount++;
                continue;
            }

            try {
                $fileResult = $this->processSingleFile($file, $userId, $tenantSlug, $requestId, $index);
                $results[] = $fileResult;
                
                if ($fileResult['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                Log::error('Bulk ingest file error', [
                    'request_id' => $requestId,
                    'file_index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failCount++;
            }
        }

        $totalTime = microtime(true) - $startTime;

        Log::info('Bulk ingest completed', [
            'request_id' => $requestId,
            'total_files' => count($files),
            'success' => $successCount,
            'failed' => $failCount,
            'total_time' => round($totalTime, 2) . 's'
        ]);

        return response()->json([
            'success' => $successCount > 0,
            'request_id' => $requestId,
            'total_files' => count($files),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results,
            'processing_time' => round($totalTime, 2),
            'message' => "$successCount de " . count($files) . " arquivo(s) processado(s) com sucesso."
        ]);
    }

    private function processSingleFile(UploadedFile $file, int $userId, string $tenantSlug, string $requestId, int $index): array
    {
        $fileStart = microtime(true);
        $originalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());
        $fileSize = $file->getSize();

        Log::info('Processing file in bulk', [
            'request_id' => $requestId,
            'index' => $index,
            'filename' => $originalName,
            'size' => $fileSize,
            'extension' => $ext
        ]);

        // Validate file size
        if ($fileSize > self::MAX_FILE_SIZE) {
            return [
                'index' => $index,
                'filename' => $originalName,
                'success' => false,
                'error' => 'Arquivo muito grande. Limite: 500MB'
            ];
        }

        // Validate page count using existing validator
        try {
            $validator = new DocumentPageValidator();
            $pageValidation = $validator->validatePageLimit($file->getPathname(), $ext);
            
            if (!$pageValidation['valid']) {
                return [
                    'index' => $index,
                    'filename' => $originalName,
                    'success' => false,
                    'error' => $pageValidation['message'],
                    'estimated_pages' => $pageValidation['estimated_pages']
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Page validation failed, continuing anyway', [
                'file' => $originalName,
                'error' => $e->getMessage()
            ]);
        }

        // Use RagController's extraction logic
        $ragController = new \App\Http\Controllers\RagController();
        
        // Create a fake request for RagController
        $fakeReq = Request::create('/api/rag/ingest', 'POST', [
            'user_id' => $userId,
            'tenant_slug' => $tenantSlug
        ]);
        $fakeReq->files->set('document', $file);
        
        try {
            $response = $ragController->ingest($fakeReq);
            $responseData = json_decode($response->getContent(), true);
            
            $fileTime = microtime(true) - $fileStart;
            
            if (isset($responseData['ok']) && $responseData['ok']) {
                return [
                    'index' => $index,
                    'filename' => $originalName,
                    'success' => true,
                    'document_id' => $responseData['document_id'] ?? null,
                    'chunks_created' => $responseData['chunks_created'] ?? 0,
                    'processing_time' => round($fileTime, 2)
                ];
            } else {
                return [
                    'index' => $index,
                    'filename' => $originalName,
                    'success' => false,
                    'error' => $responseData['error'] ?? 'Erro desconhecido'
                ];
            }
        } catch (\Exception $e) {
            return [
                'index' => $index,
                'filename' => $originalName,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

