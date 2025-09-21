<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class UniversalDocumentExtractor
{
    private string $extractorPath;
    private array $supportedFormats = ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'csv', 'html', 'xml'];

    public function __construct()
    {
        $this->extractorPath = base_path('scripts/document_extraction/main_extractor.py');
    }

    /**
     * Extract content from a document file
     *
     * @param string $filePath Absolute path to the file to extract
     * @return array Extraction result with text, metadata, and quality metrics
     * @throws Exception
     */
    public function extract(string $filePath): array
    {
        // Validate file exists
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        // Validate extractor script exists
        if (!file_exists($this->extractorPath)) {
            throw new Exception("Universal extractor script not found: {$this->extractorPath}");
        }

        try {
            // Execute Python extraction script
            $command = sprintf('python3 %s %s 2>&1',
                escapeshellarg($this->extractorPath),
                escapeshellarg($filePath)
            );

            $output = shell_exec($command);

            if ($output === null) {
                throw new Exception("Failed to execute extraction script");
            }

            // Parse JSON response
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Universal document extraction JSON decode error', [
                    'file_path' => $filePath,
                    'output' => $output,
                    'json_error' => json_last_error_msg()
                ]);
                throw new Exception("Failed to parse extraction result: " . json_last_error_msg());
            }

            // Log extraction results for monitoring
            $this->logExtractionResult($filePath, $result);

            return $result;

        } catch (Exception $e) {
            Log::error('Universal document extraction failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Extract content with format validation
     *
     * @param string $filePath
     * @param string|null $expectedFormat Optional format validation
     * @return array
     * @throws Exception
     */
    public function extractWithValidation(string $filePath, ?string $expectedFormat = null): array
    {
        $result = $this->extract($filePath);

        // Validate expected format if provided
        if ($expectedFormat && isset($result['file_type'])) {
            if ($result['file_type'] !== $expectedFormat) {
                Log::warning('Document format mismatch', [
                    'file_path' => $filePath,
                    'expected_format' => $expectedFormat,
                    'detected_format' => $result['file_type']
                ]);
            }
        }

        return $result;
    }

    /**
     * Get supported file formats
     *
     * @return array
     */
    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Check if a file format is supported
     *
     * @param string $format File extension without dot (e.g., 'pdf', 'docx')
     * @return bool
     */
    public function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), $this->supportedFormats);
    }

    /**
     * Get extraction quality assessment
     *
     * @param array $extractionResult
     * @return array Quality assessment with status and recommendations
     */
    public function getQualityAssessment(array $extractionResult): array
    {
        return [
            'quality_status' => $extractionResult['quality_status'] ?? 'UNKNOWN',
            'extraction_percentage' => $extractionResult['quality_metrics']['extraction_percentage'] ?? 0,
            'problems' => $extractionResult['problems_identified'] ?? [],
            'recommendations' => $extractionResult['recommendations'] ?? [],
            'text_length' => $extractionResult['quality_metrics']['text_length'] ?? 0,
            'word_count' => $extractionResult['quality_metrics']['word_count'] ?? 0
        ];
    }

    /**
     * Check if extraction was successful
     *
     * @param array $extractionResult
     * @return bool
     */
    public function isExtractionSuccessful(array $extractionResult): bool
    {
        return ($extractionResult['status'] ?? '') === 'success' &&
               !empty($extractionResult['extracted_text']);
    }

    /**
     * Get extraction statistics
     *
     * @param array $extractionResult
     * @return array
     */
    public function getExtractionStats(array $extractionResult): array
    {
        $metrics = $extractionResult['metrics'] ?? [];
        $metadata = $extractionResult['metadata'] ?? [];

        return [
            'file_type' => $extractionResult['file_type'] ?? 'unknown',
            'total_elements' => $metrics['total_elements'] ?? 0,
            'extracted_elements' => $metrics['extracted_elements'] ?? 0,
            'failed_elements' => $metrics['failed_elements'] ?? 0,
            'extraction_percentage' => $metrics['extraction_percentage'] ?? 0,
            'text_length' => strlen($extractionResult['extracted_text'] ?? ''),
            'metadata' => $metadata
        ];
    }

    /**
     * Create standardized error response
     *
     * @param string $errorMessage
     * @return array
     */
    private function createErrorResponse(string $errorMessage): array
    {
        return [
            'status' => 'error',
            'error' => $errorMessage,
            'extracted_text' => '',
            'metadata' => [],
            'metrics' => [
                'total_elements' => 0,
                'extracted_elements' => 0,
                'failed_elements' => 0,
                'extraction_percentage' => 0.0
            ],
            'quality_metrics' => [
                'extraction_percentage' => 0,
                'text_length' => 0,
                'word_count' => 0,
                'line_count' => 0,
                'character_variety' => 0,
                'content_density' => 0,
                'structure_preservation_score' => 0,
                'average_words_per_line' => 0
            ],
            'quality_status' => 'FAILED',
            'problems_identified' => ['extraction_failed'],
            'recommendations' => ['Check error message and file format compatibility']
        ];
    }

    /**
     * Log extraction results for monitoring and debugging
     *
     * @param string $filePath
     * @param array $result
     */
    private function logExtractionResult(string $filePath, array $result): void
    {
        $logData = [
            'file_path' => $filePath,
            'file_type' => $result['file_type'] ?? 'unknown',
            'status' => $result['status'] ?? 'unknown',
            'text_length' => strlen($result['extracted_text'] ?? ''),
            'quality_status' => $result['quality_status'] ?? 'unknown',
            'extraction_percentage' => $result['quality_metrics']['extraction_percentage'] ?? 0
        ];

        if ($result['status'] === 'error') {
            Log::error('Document extraction failed', array_merge($logData, [
                'error' => $result['error'] ?? 'Unknown error'
            ]));
        } else {
            Log::info('Document extraction completed', $logData);
        }
    }

    /**
     * Batch extract multiple files
     *
     * @param array $filePaths Array of file paths to extract
     * @param bool $continueOnError Whether to continue processing if one file fails
     * @return array Results indexed by file path
     */
    public function extractBatch(array $filePaths, bool $continueOnError = true): array
    {
        $results = [];

        foreach ($filePaths as $filePath) {
            try {
                $results[$filePath] = $this->extract($filePath);
            } catch (Exception $e) {
                $results[$filePath] = $this->createErrorResponse($e->getMessage());

                if (!$continueOnError) {
                    break;
                }
            }
        }

        return $results;
    }
}