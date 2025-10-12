<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExcelStructuredService
{
    /**
     * Extract structured data from Excel file
     * Returns both text (for RAG) and JSON (for queries)
     */
    public function extractStructured(string $filePath): array
    {
        try {
            $scriptPath = base_path('scripts/document_extraction/excel_structured_extractor.py');
            
            $process = new Process(['python3', $scriptPath, $filePath]);
            $process->setTimeout(300); // 5 minutes for large files
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (!$result || !isset($result['success'])) {
                Log::error('Excel structured extraction failed: invalid output', [
                    'output' => $output
                ]);
                return [
                    'success' => false,
                    'text' => '',
                    'structured_data' => null
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Excel structured extraction error', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);

            return [
                'success' => false,
                'text' => '',
                'structured_data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Query structured Excel data
     * Supports aggregations, filters, and complex queries
     */
    public function queryStructured(array $structuredData, string $query): array
    {
        if (!$structuredData || empty($structuredData['sheets'])) {
            return [
                'success' => false,
                'error' => 'No structured data available'
            ];
        }

        // Detect query type
        $queryType = $this->detectQueryType($query);
        
        switch ($queryType) {
            case 'aggregation':
                return $this->handleAggregation($structuredData, $query);
            
            case 'filter':
                return $this->handleFilter($structuredData, $query);
            
            case 'search':
                return $this->handleSearch($structuredData, $query);
            
            default:
                return [
                    'success' => false,
                    'error' => 'Could not determine query type'
                ];
        }
    }

    /**
     * Detect query type from natural language
     */
    private function detectQueryType(string $query): string
    {
        $lower = strtolower($query);
        
        // Aggregation keywords
        if (preg_match('/(soma|total|média|média|count|quantos|quanto|somar|calcular)/i', $query)) {
            return 'aggregation';
        }
        
        // Filter keywords
        if (preg_match('/(maior que|menor que|entre|igual|diferente|>|<|=)/i', $query)) {
            return 'filter';
        }
        
        // Default to search
        return 'search';
    }

    /**
     * Handle aggregation queries (SUM, AVG, COUNT, etc)
     */
    private function handleAggregation(array $structuredData, string $query): array
    {
        try {
            $operation = $this->detectOperation($query);
            $column = $this->detectColumn($query, $structuredData);
            
            if (!$column) {
                return [
                    'success' => false,
                    'error' => 'Could not identify column for aggregation'
                ];
            }

            $values = $this->extractColumnValues($structuredData, $column);
            
            switch ($operation) {
                case 'sum':
                    $result = array_sum($values);
                    break;
                case 'avg':
                    $result = count($values) > 0 ? array_sum($values) / count($values) : 0;
                    break;
                case 'count':
                    $result = count($values);
                    break;
                case 'max':
                    $result = count($values) > 0 ? max($values) : null;
                    break;
                case 'min':
                    $result = count($values) > 0 ? min($values) : null;
                    break;
                default:
                    $result = count($values);
            }

            return [
                'success' => true,
                'operation' => $operation,
                'column' => $column,
                'result' => $result,
                'row_count' => count($values)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle filter queries
     */
    private function handleFilter(array $structuredData, string $query): array
    {
        // Simplified filter implementation
        // In production, this could use a query builder
        
        $allRows = [];
        foreach ($structuredData['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $row) {
                $allRows[] = array_merge(['_sheet' => $sheet['name']], $row);
            }
        }

        return [
            'success' => true,
            'total_rows' => count($allRows),
            'message' => 'Filter functionality available - use LLM with structured data'
        ];
    }

    /**
     * Handle search queries
     */
    private function handleSearch(array $structuredData, string $query): array
    {
        $lower = strtolower($query);
        $results = [];

        foreach ($structuredData['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $rowIdx => $row) {
                foreach ($row as $column => $value) {
                    if (stripos((string)$value, $query) !== false) {
                        $results[] = [
                            'sheet' => $sheet['name'],
                            'row' => $rowIdx + 2, // +2 because header is row 1
                            'column' => $column,
                            'value' => $value
                        ];
                    }
                }
            }
        }

        return [
            'success' => true,
            'matches' => count($results),
            'results' => array_slice($results, 0, 10) // Limit to 10 results
        ];
    }

    /**
     * Detect operation type from query
     */
    private function detectOperation(string $query): string
    {
        $lower = strtolower($query);
        
        if (preg_match('/(soma|total|somar)/i', $query)) return 'sum';
        if (preg_match('/(média|media|average)/i', $query)) return 'avg';
        if (preg_match('/(quantos|count|contar)/i', $query)) return 'count';
        if (preg_match('/(maior|máximo|maximo|max)/i', $query)) return 'max';
        if (preg_match('/(menor|mínimo|minimo|min)/i', $query)) return 'min';
        
        return 'count';
    }

    /**
     * Detect column name from query
     */
    private function detectColumn(string $query, array $structuredData): ?string
    {
        $lower = strtolower($query);
        
        // Get all possible column names
        $columns = [];
        foreach ($structuredData['sheets'] as $sheet) {
            $columns = array_merge($columns, $sheet['headers']);
        }
        $columns = array_unique($columns);

        // Try to find column mention in query
        foreach ($columns as $column) {
            if (stripos($query, $column) !== false) {
                return $column;
            }
        }

        // Fallback: find numeric column for aggregations
        foreach ($structuredData['sheets'] as $sheet) {
            if (empty($sheet['rows'])) continue;
            
            foreach ($sheet['headers'] as $header) {
                $firstValue = $sheet['rows'][0][$header] ?? null;
                if (is_numeric($firstValue)) {
                    return $header;
                }
            }
        }

        return null;
    }

    /**
     * Extract all values from a specific column
     */
    private function extractColumnValues(array $structuredData, string $column): array
    {
        $values = [];

        foreach ($structuredData['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $row) {
                if (isset($row[$column]) && is_numeric($row[$column])) {
                    $values[] = (float)$row[$column];
                }
            }
        }

        return $values;
    }

    /**
     * Create intelligent chunks from Excel/CSV data
     * Each row becomes a chunk with headers preserved
     */
    public function createIntelligentChunks(array $structuredData): array
    {
        $chunks = [];

        // Check if it's Excel (with sheets) or CSV (direct data)
        if (isset($structuredData['sheets'])) {
            // Excel format
            foreach ($structuredData['sheets'] as $sheet) {
                $sheetName = $sheet['name'];
                $headers = $sheet['headers'];
                
                foreach ($sheet['rows'] as $rowIdx => $row) {
                    // Create chunk text: "Header1: Value1 | Header2: Value2 | ..."
                    $chunkParts = [];
                    $chunkParts[] = "Sheet: {$sheetName}";
                    
                    foreach ($headers as $header) {
                        $value = $row[$header] ?? '';
                        if ($value !== '' && $value !== null) {
                            $chunkParts[] = "{$header}: {$value}";
                        }
                    }
                    
                    $chunkText = implode(' | ', $chunkParts);
                    
                    if (trim($chunkText)) {
                        $chunks[] = $chunkText;
                    }
                }
            }
        } elseif (isset($structuredData['headers']) && isset($structuredData['rows'])) {
            // CSV format (direct data, no sheets)
            $headers = $structuredData['headers'];
            
            foreach ($structuredData['rows'] as $rowIdx => $row) {
                $chunkParts = [];
                
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    if ($value !== '' && $value !== null) {
                        $chunkParts[] = "{$header}: {$value}";
                    }
                }
                
                $chunkText = implode(' | ', $chunkParts);
                
                if (trim($chunkText)) {
                    $chunks[] = $chunkText;
                }
            }
        }

        return $chunks;
    }
}

