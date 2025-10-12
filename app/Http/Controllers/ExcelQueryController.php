<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ExcelStructuredService;

class ExcelQueryController extends Controller
{
    protected $excelService;

    public function __construct(ExcelStructuredService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Query Excel document using structured data
     * Supports aggregations, filters, and complex queries
     */
    public function query(Request $request)
    {
        $documentId = $request->input('document_id');
        $query = $request->input('query');

        if (!$documentId || !$query) {
            return response()->json([
                'success' => false,
                'error' => 'document_id and query are required'
            ], 400);
        }

        try {
            // Get document metadata
            $document = DB::table('documents')->where('id', $documentId)->first(['id', 'title', 'metadata']);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => 'Document not found'
                ], 404);
            }

            $metadata = json_decode($document->metadata, true);

            // Check if document has structured data
            if (!isset($metadata['structured_data'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Document does not have structured data. Only Excel files with structured extraction are supported.',
                    'suggestion' => 'Use regular RAG search for this document type'
                ], 400);
            }

            $structuredData = $metadata['structured_data'];

            // Perform structured query
            $result = $this->excelService->queryStructured($structuredData, $query);

            return response()->json(array_merge($result, [
                'document_id' => $documentId,
                'document_title' => $document->title,
                'query' => $query
            ]));

        } catch (\Exception $e) {
            Log::error('Excel structured query failed', [
                'document_id' => $documentId,
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Query processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get structured metadata for a document
     */
    public function getStructuredData(Request $request, int $documentId)
    {
        try {
            $document = DB::table('documents')->where('id', $documentId)->first(['id', 'title', 'metadata']);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'error' => 'Document not found'
                ], 404);
            }

            $metadata = json_decode($document->metadata, true);

            if (!isset($metadata['structured_data'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Document does not have structured data'
                ], 400);
            }

            $structuredData = $metadata['structured_data'];

            // Return summary (not full data to avoid huge response)
            $summary = [
                'document_id' => $documentId,
                'document_title' => $document->title,
                'total_sheets' => $structuredData['metadata']['total_sheets'],
                'total_rows' => $structuredData['metadata']['total_rows'],
                'sheets' => []
            ];

            foreach ($structuredData['sheets'] as $sheet) {
                $summary['sheets'][] = [
                    'name' => $sheet['name'],
                    'headers' => $sheet['headers'],
                    'row_count' => $sheet['row_count'],
                    'column_count' => $sheet['column_count'],
                    'sample_row' => $sheet['rows'][0] ?? null
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get structured data', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

