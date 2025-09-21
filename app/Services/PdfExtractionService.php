<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class PdfExtractionService
{
    protected $pythonPath;
    protected $scriptPath;
    
    public function __construct()
    {
        $this->pythonPath = env('PYTHON_PATH', '/usr/bin/python3');
        $this->scriptPath = base_path('scripts/pdf_extraction/extract_pdf.py');
    }
    
    public function extractWithQuality(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("Arquivo PDF não encontrado: {$pdfPath}");
        }
        
        if (!file_exists($this->scriptPath)) {
            throw new Exception("Script Python não encontrado: {$this->scriptPath}");
        }
        
        $result = Process::timeout(300)->run([
            $this->pythonPath,
            $this->scriptPath,
            $pdfPath
        ]);
        
        if (!$result->successful()) {
            Log::error('PdfExtractionService Error', [
                'pdf' => $pdfPath,
                'error' => $result->errorOutput(),
                'output' => $result->output()
            ]);
            
            return [
                'success' => false,
                'error' => 'Falha na extração: ' . $result->errorOutput()
            ];
        }
        
        $data = json_decode($result->output(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('PdfExtractionService JSON Error', [
                'pdf' => $pdfPath,
                'output' => substr($result->output(), 0, 1000)
            ]);
            
            return [
                'success' => false,
                'error' => 'Resposta inválida do extrator'
            ];
        }
        
        Log::info('PDF Extraction Success', [
            'pdf' => basename($pdfPath),
            'pages' => $data['extraction_stats']['total_pages'] ?? 0,
            'extraction_percentage' => $data['quality_report']['extraction_percentage'] ?? 0,
            'status' => $data['quality_report']['status'] ?? 'unknown'
        ]);
        
        return $data;
    }
    
    public function extractTextForRAG(string $pdfPath): ?string
    {
        $result = $this->extractWithQuality($pdfPath);
        
        if (!$result['success']) {
            return null;
        }
        
        return $result['content']['full_text'] ?? '';
    }
    
    public function getQualityReport(array $extractionResult): array
    {
        return [
            'status' => $extractionResult['quality_report']['status'] ?? 'UNKNOWN',
            'message' => $extractionResult['quality_report']['message'] ?? '',
            'percentage' => $extractionResult['quality_report']['extraction_percentage'] ?? 0,
            'pages_total' => $extractionResult['extraction_stats']['total_pages'] ?? 0,
            'pages_extracted' => $extractionResult['extraction_stats']['text_pages'] ?? 0,
            'tables_found' => $extractionResult['extraction_stats']['tables_found'] ?? 0,
            'images_found' => $extractionResult['extraction_stats']['images_found'] ?? 0,
            'review_needed' => $extractionResult['quality_report']['pages_needing_review'] ?? []
        ];
    }
}
