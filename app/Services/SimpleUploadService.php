<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Simple, guaranteed-to-work upload service
 * No complex dependencies - just file upload, text extraction, and basic chunking
 */
class SimpleUploadService
{
    private const MAX_CHUNK_SIZE = 2000;
    private const BYPASS_STORAGE_PATH = 'bypass-uploads';

    /**
     * Process file with minimal complexity - guaranteed success
     */
    public function processFile(UploadedFile $file, string $title, int $userId): array
    {
        $startTime = microtime(true);

        Log::info('SimpleUploadService: Processing started', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'user_id' => $userId
        ]);

        try {
            // 1. Store file safely
            $storedPath = $this->storeFile($file);

            // 2. Extract text content
            $content = $this->extractTextContent($file, $storedPath);

            // 3. Create document record
            $documentId = $this->createDocument($title, $content, $userId, $storedPath);

            // 4. Create simple chunks
            $chunksCreated = $this->createSimpleChunks($documentId, $content);

            $processingTime = microtime(true) - $startTime;

            Log::info('SimpleUploadService: Processing completed', [
                'document_id' => $documentId,
                'chunks_created' => $chunksCreated,
                'processing_time' => round($processingTime, 3) . 's',
                'content_length' => strlen($content)
            ]);

            return [
                'success' => true,
                'document_id' => $documentId,
                'chunks_created' => $chunksCreated,
                'processing_time' => round($processingTime, 3),
                'content_length' => strlen($content),
                'method' => 'simple_bypass'
            ];

        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;

            Log::error('SimpleUploadService: Processing failed', [
                'error' => $e->getMessage(),
                'processing_time' => round($processingTime, 3) . 's',
                'user_id' => $userId
            ]);

            throw new Exception('Processamento simples falhou: ' . $e->getMessage());
        }
    }

    /**
     * Store uploaded file safely
     */
    private function storeFile(UploadedFile $file): string
    {
        // Ensure bypass directory exists
        if (!Storage::exists(self::BYPASS_STORAGE_PATH)) {
            Storage::makeDirectory(self::BYPASS_STORAGE_PATH);
        }

        $filename = time() . '_' . $file->getClientOriginalName();
        $storedPath = $file->storeAs(self::BYPASS_STORAGE_PATH, $filename);

        Log::info('File stored via bypass', [
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'size' => $file->getSize()
        ]);

        return $storedPath;
    }

    /**
     * Extract text content using simple methods
     */
    private function extractTextContent(UploadedFile $file, string $storedPath): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = '';

        try {
            switch ($extension) {
                case 'txt':
                case 'text':
                    $content = Storage::get($storedPath);
                    break;

                case 'pdf':
                    $content = $this->extractFromPdfSimple($storedPath);
                    break;

                case 'doc':
                case 'docx':
                    $content = $this->extractFromWordSimple($storedPath);
                    break;

                case 'html':
                case 'htm':
                    $htmlContent = Storage::get($storedPath);
                    $content = strip_tags($htmlContent);
                    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    break;

                case 'csv':
                    $content = $this->extractFromCsvSimple($storedPath);
                    break;

                case 'json':
                    $jsonContent = Storage::get($storedPath);
                    $data = json_decode($jsonContent, true);
                    $content = $this->arrayToText($data);
                    break;

                default:
                    // Fallback: try to read as text
                    $content = Storage::get($storedPath);
                    break;
            }

            if (empty(trim($content))) {
                throw new Exception("Não foi possível extrair conteúdo do arquivo .{$extension}");
            }

            Log::info('Content extracted successfully', [
                'extension' => $extension,
                'content_length' => strlen($content),
                'method' => 'simple_extraction'
            ]);

            return trim($content);

        } catch (Exception $e) {
            Log::warning('Content extraction failed, using filename as content', [
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            // Ultimate fallback: use filename and basic info as content
            return "Documento: {$file->getClientOriginalName()}\nTipo: {$extension}\nTamanho: {$file->getSize()} bytes\nCarregado via upload bypass.";
        }
    }

    /**
     * Enhanced PDF extraction optimized for structured content
     */
    private function extractFromPdfSimple(string $storedPath): string
    {
        $fullPath = Storage::path($storedPath);

        Log::info('PDF extraction started', [
            'file_path' => $storedPath,
            'file_size' => Storage::size($storedPath)
        ]);

        // Try different pdftotext options for better structure preservation
        $methods = [
            // Method 1: Raw text (preserves structure better)
            "pdftotext -raw '{$fullPath}' - 2>/dev/null",
            // Method 2: Layout preservation
            "pdftotext -layout '{$fullPath}' - 2>/dev/null",
            // Method 3: Simple extraction
            "pdftotext '{$fullPath}' - 2>/dev/null"
        ];

        foreach ($methods as $i => $command) {
            $output = shell_exec($command);
            if (!empty($output) && strlen(trim($output)) > 50) {
                Log::info('PDF extraction successful', [
                    'method' => $i + 1,
                    'content_length' => strlen($output),
                    'command' => explode(' ', $command)[0]
                ]);

                // Post-process extracted text for better structure
                return $this->postProcessPdfContent($output);
            }
        }

        throw new Exception('PDF extraction tools not available or file is empty');
    }

    /**
     * Post-process PDF content to improve structure recognition
     */
    private function postProcessPdfContent(string $content): string
    {
        // Clean up common PDF extraction artifacts
        $content = preg_replace('/\f/', "\n", $content); // Form feeds to newlines
        $content = preg_replace('/\s*\n\s*\n\s*/', "\n\n", $content); // Multiple newlines to double

        // Improve numbered list recognition
        $content = preg_replace('/(\d+)[\s]*[\.\)\-][\s]*([^\n])/m', "$1. $2", $content);

        // Ensure proper spacing around list items
        $content = preg_replace('/(?<=\n)(\d+\.\s)/m', "\n$1", $content);

        // Clean up extra whitespace but preserve structure
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = trim($content);

        Log::info('PDF content post-processed', [
            'original_length' => strlen($content),
            'has_numbered_lists' => preg_match('/\n\d+\.\s/', $content) ? 'yes' : 'no',
            'paragraph_count' => substr_count($content, "\n\n") + 1
        ]);

        return $content;
    }

    /**
     * Simple Word extraction
     */
    private function extractFromWordSimple(string $storedPath): string
    {
        // For now, return a placeholder - can be enhanced later
        throw new Exception('Word document extraction not implemented in simple mode');
    }

    /**
     * Simple CSV extraction
     */
    private function extractFromCsvSimple(string $storedPath): string
    {
        $fullPath = Storage::path($storedPath);
        $content = '';

        if (($handle = fopen($fullPath, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $content .= implode(" | ", $data) . "\n";
            }
            fclose($handle);
        }

        return $content;
    }

    /**
     * Convert array to readable text
     */
    private function arrayToText($data, $depth = 0): string
    {
        if (!is_array($data)) {
            return (string) $data;
        }

        $text = '';
        $indent = str_repeat('  ', $depth);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $text .= "{$indent}{$key}:\n" . $this->arrayToText($value, $depth + 1);
            } else {
                $text .= "{$indent}{$key}: {$value}\n";
            }
        }

        return $text;
    }

    /**
     * Create document record in database
     */
    private function createDocument(string $title, string $content, int $userId, string $storedPath): int
    {
        // Get user email to use as tenant_slug for consistency
        $user = DB::table('users')->where('id', $userId)->first();
        $tenantSlug = $user ? $user->email : 'user_' . $userId;

        $documentId = DB::table('documents')->insertGetId([
            'title' => $title,
            'source' => 'bypass_upload',
            'tenant_slug' => $tenantSlug,
            'metadata' => json_encode([
                'upload_method' => 'simple_bypass',
                'stored_path' => $storedPath,
                'content_length' => strlen($content),
                'processed_at' => now()->toISOString(),
                'processing_version' => 'bypass_v1.0'
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Log::info('Document created via bypass', [
            'document_id' => $documentId,
            'title' => $title,
            'user_id' => $userId
        ]);

        return $documentId;
    }

    /**
     * Create intelligent chunks with keyword indexing
     */
    private function createSimpleChunks(int $documentId, string $content): int
    {
        $chunks = $this->intelligentChunking($content);
        $chunksCreated = 0;

        foreach ($chunks as $index => $chunkData) {
            if (strlen(trim($chunkData['content'])) > 20) {
                // Extract keywords and metadata for better search
                $keywords = $this->extractKeywords($chunkData['content']);
                $metadata = $this->analyzeChunkContent($chunkData['content'], $index);

                DB::table('chunks')->insert([
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'ord' => $index,
                    'content' => $chunkData['content'],
                    'embedding' => null, // No embeddings for speed
                    'meta' => json_encode(array_merge($chunkData['metadata'], $metadata, [
                        'keywords' => $keywords,
                        'chunk_type' => $chunkData['type'],
                        'word_count' => str_word_count($chunkData['content']),
                        'has_numbers' => $this->hasNumbers($chunkData['content']),
                        'has_list_items' => $this->hasListItems($chunkData['content'])
                    ])),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $chunksCreated++;
            }
        }

        // Guarantee at least 1 chunk
        if ($chunksCreated === 0) {
            $keywords = $this->extractKeywords($content);
            DB::table('chunks')->insert([
                'document_id' => $documentId,
                'chunk_index' => 0,
                'ord' => 0,
                'content' => substr($content, 0, self::MAX_CHUNK_SIZE),
                'embedding' => null,
                'meta' => json_encode([
                    'keywords' => $keywords,
                    'chunk_type' => 'fallback',
                    'word_count' => str_word_count($content),
                    'is_truncated' => strlen($content) > self::MAX_CHUNK_SIZE
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $chunksCreated = 1;
        }

        Log::info('Intelligent chunks created via bypass', [
            'document_id' => $documentId,
            'chunks_created' => $chunksCreated,
            'content_length' => strlen($content),
            'chunking_method' => 'intelligent_bypass'
        ]);

        return $chunksCreated;
    }

    /**
     * Intelligent chunking that preserves structure and context
     */
    private function intelligentChunking(string $text): array
    {
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim($text);

        $chunks = [];

        // First, try to identify and chunk by structure
        $structuredChunks = $this->chunkByStructure($text);

        if (count($structuredChunks) > 1) {
            return $structuredChunks;
        }

        // Fallback to paragraph-based chunking
        return $this->chunkByParagraphs($text);
    }

    /**
     * Chunk by document structure (sections, lists, etc.)
     */
    private function chunkByStructure(string $text): array
    {
        $chunks = [];

        // Detect numbered lists (like "30 motivos")
        if (preg_match_all('/(?:^|\n)(\d+[\.\)\-\s][^\n]+(?:\n(?!\d+[\.\)\-\s])[^\n]*)*)/m', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $lastEnd = 0;

            foreach ($matches[0] as $i => $match) {
                $start = $match[1];
                $content = $match[0];

                // Add content before this list item if any
                if ($start > $lastEnd) {
                    $beforeContent = trim(substr($text, $lastEnd, $start - $lastEnd));
                    if (strlen($beforeContent) > 50) {
                        $chunks[] = [
                            'content' => $beforeContent,
                            'type' => 'intro_section',
                            'metadata' => ['section_type' => 'introduction']
                        ];
                    }
                }

                // Add the numbered item
                $chunks[] = [
                    'content' => trim($content),
                    'type' => 'numbered_item',
                    'metadata' => [
                        'item_number' => (int)$matches[1][$i][0],
                        'section_type' => 'numbered_list'
                    ]
                ];

                $lastEnd = $start + strlen($content);
            }

            // Add remaining content
            if ($lastEnd < strlen($text)) {
                $remaining = trim(substr($text, $lastEnd));
                if (strlen($remaining) > 50) {
                    $chunks[] = [
                        'content' => $remaining,
                        'type' => 'conclusion_section',
                        'metadata' => ['section_type' => 'conclusion']
                    ];
                }
            }

            return $chunks;
        }

        // Detect bullet lists
        if (preg_match_all('/(?:^|\n)([\-\*\•][^\n]+(?:\n(?![\-\*\•])[^\n]*)*)/m', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $lastEnd = 0;

            foreach ($matches[0] as $match) {
                $start = $match[1];
                $content = $match[0];

                // Add content before this bullet item if any
                if ($start > $lastEnd) {
                    $beforeContent = trim(substr($text, $lastEnd, $start - $lastEnd));
                    if (strlen($beforeContent) > 50) {
                        $chunks[] = [
                            'content' => $beforeContent,
                            'type' => 'text_section',
                            'metadata' => ['section_type' => 'paragraph']
                        ];
                    }
                }

                // Add the bullet item
                $chunks[] = [
                    'content' => trim($content),
                    'type' => 'bullet_item',
                    'metadata' => ['section_type' => 'bullet_list']
                ];

                $lastEnd = $start + strlen($content);
            }

            return $chunks;
        }

        // No clear structure found
        return [];
    }

    /**
     * Chunk by paragraphs with smart sentence preservation
     */
    private function chunkByParagraphs(string $text): array
    {
        $chunks = [];
        $paragraphs = preg_split('/\n\s*\n/', $text);

        $currentChunk = '';
        $chunkIndex = 0;
        $targetSize = 800; // Smaller chunks for better search

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            // If adding this paragraph would make chunk too big
            if (strlen($currentChunk) + strlen($paragraph) > $targetSize && !empty($currentChunk)) {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'type' => 'paragraph_group',
                    'metadata' => [
                        'paragraph_count' => substr_count($currentChunk, "\n\n") + 1,
                        'section_type' => 'paragraph_group'
                    ]
                ];
                $currentChunk = $paragraph;
                $chunkIndex++;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : "\n\n") . $paragraph;
            }
        }

        // Add final chunk
        if (!empty($currentChunk)) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'type' => 'paragraph_group',
                'metadata' => [
                    'paragraph_count' => substr_count($currentChunk, "\n\n") + 1,
                    'section_type' => 'paragraph_group'
                ]
            ];
        }

        return empty($chunks) ? [[
            'content' => $text,
            'type' => 'single_chunk',
            'metadata' => ['section_type' => 'complete_document']
        ]] : $chunks;
    }

    /**
     * Extract keywords for better search
     */
    private function extractKeywords(string $content): array
    {
        $text = strtolower($content);

        // Remove common Portuguese stop words
        $stopWords = ['de', 'da', 'do', 'das', 'dos', 'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas',
                     'para', 'com', 'sem', 'por', 'em', 'na', 'no', 'nas', 'nos', 'que', 'é', 'são',
                     'foi', 'será', 'ter', 'tem', 'teve', 'e', 'ou', 'mas', 'se', 'não', 'sim'];

        // Extract words (3+ characters)
        preg_match_all('/\b[a-záàâãéêíóôõúç]{3,}\b/u', $text, $matches);
        $words = $matches[0];

        // Filter out stop words and get frequency
        $keywords = [];
        foreach ($words as $word) {
            if (!in_array($word, $stopWords)) {
                $keywords[$word] = ($keywords[$word] ?? 0) + 1;
            }
        }

        // Sort by frequency and return top 10
        arsort($keywords);
        return array_keys(array_slice($keywords, 0, 10));
    }

    /**
     * Analyze chunk content for metadata
     */
    private function analyzeChunkContent(string $content, int $index): array
    {
        return [
            'sentence_count' => substr_count($content, '.') + substr_count($content, '!') + substr_count($content, '?'),
            'paragraph_count' => substr_count($content, "\n\n") + 1,
            'chunk_index' => $index,
            'content_hash' => substr(md5($content), 0, 8)
        ];
    }

    /**
     * Check if content has numbers/numbered items
     */
    private function hasNumbers(string $content): bool
    {
        return preg_match('/\d+/', $content) === 1;
    }

    /**
     * Check if content has list items
     */
    private function hasListItems(string $content): bool
    {
        return preg_match('/(?:^|\n)[\d\-\*\•][\.\)\-\s]/', $content) === 1;
    }

    /**
     * Legacy simple chunking method (keeping for compatibility)
     */
    private function simpleChunking(string $text): array
    {
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim($text);

        $length = strlen($text);
        $chunks = [];
        $chunkSize = self::MAX_CHUNK_SIZE;
        $overlap = 200;

        $start = 0;
        while ($start < $length) {
            $end = min($length, $start + $chunkSize);
            $chunk = substr($text, $start, $end - $start);

            if (strlen(trim($chunk)) > 0) {
                $chunks[] = trim($chunk);
            }

            if ($end >= $length) break;
            $start = $end - $overlap;
        }

        return empty($chunks) ? [trim($text)] : $chunks;
    }

    /**
     * Search in bypass documents using robust textual search
     */
    public function searchBypassDocuments(string $query, string $tenantSlug, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $documentId = $options['document_id'] ?? null;
        $threshold = $options['threshold'] ?? 0.1;

        try {
            // Build base query for bypass documents (without embeddings)
            $chunksQuery = DB::table('chunks')
                ->join('documents', 'chunks.document_id', '=', 'documents.id')
                ->where('documents.tenant_slug', $tenantSlug)
                ->where('documents.source', 'bypass_upload')
                ->whereNull('chunks.embedding')
                ->select([
                    'chunks.id',
                    'chunks.content',
                    'chunks.document_id',
                    'documents.title',
                    'chunks.ord',
                    'chunks.meta'
                ]);

            if ($documentId) {
                $chunksQuery->where('chunks.document_id', $documentId);
            }

            $allChunks = $chunksQuery->get();

            if ($allChunks->isEmpty()) {
                return [
                    'success' => true,
                    'results' => [],
                    'total' => 0,
                    'query' => $query,
                    'method' => 'bypass_textual_search'
                ];
            }

            // Multi-strategy textual search
            $results = $this->performAdvancedTextualSearch($query, $allChunks, $threshold);

            // Sort by relevance and limit
            $results = array_slice($results, 0, $limit);

            return [
                'success' => true,
                'results' => $results,
                'total' => count($results),
                'query' => $query,
                'method' => 'bypass_textual_search',
                'strategies_used' => array_unique(array_column($results, 'strategy'))
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => 'bypass_textual_search'
            ];
        }
    }

    /**
     * Advanced textual search using multiple strategies
     */
    private function performAdvancedTextualSearch(string $query, $chunks, float $threshold): array
    {
        $results = [];
        $query = trim(strtolower($query));
        $queryWords = $this->extractSearchTerms($query);

        foreach ($chunks as $chunk) {
            $content = strtolower($chunk->content);
            $scores = [];

            // Strategy 1: Exact phrase match (highest priority)
            if (strpos($content, $query) !== false) {
                $scores[] = ['score' => 1.0, 'strategy' => 'exact_phrase'];
            }

            // Strategy 2: Partial phrase match
            $partialScore = $this->calculatePartialPhraseScore($query, $content);
            if ($partialScore > 0) {
                $scores[] = ['score' => $partialScore * 0.9, 'strategy' => 'partial_phrase'];
            }

            // Strategy 3: Word-based scoring with position weight
            $wordScore = $this->calculateWordScore($queryWords, $content, $chunk->content);
            if ($wordScore > 0) {
                $scores[] = ['score' => $wordScore * 0.8, 'strategy' => 'word_match'];
            }

            // Strategy 4: Fuzzy matching for numbered items
            if (preg_match('/\d+\s*motivos?/i', $query)) {
                $fuzzyScore = $this->calculateFuzzyScore($query, $content);
                if ($fuzzyScore > 0) {
                    $scores[] = ['score' => $fuzzyScore * 0.7, 'strategy' => 'fuzzy_numbered'];
                }
            }

            // Strategy 5: Semantic proximity (word distance)
            $proximityScore = $this->calculateProximityScore($queryWords, $content);
            if ($proximityScore > 0) {
                $scores[] = ['score' => $proximityScore * 0.6, 'strategy' => 'proximity'];
            }

            // Get best score for this chunk
            if (!empty($scores)) {
                $bestMatch = array_reduce($scores, function($carry, $item) {
                    return $carry === null || $item['score'] > $carry['score'] ? $item : $carry;
                });

                if ($bestMatch['score'] >= $threshold) {
                    $results[] = [
                        'id' => $chunk->id,
                        'content' => $chunk->content,
                        'document_id' => $chunk->document_id,
                        'document_title' => $chunk->title,
                        'score' => $bestMatch['score'],
                        'strategy' => $bestMatch['strategy'],
                        'ord' => $chunk->ord,
                        'type' => 'textual_match'
                    ];
                }
            }
        }

        // Sort by score desc
        usort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    private function extractSearchTerms(string $query): array
    {
        // Remove stop words and extract meaningful terms
        $stopWords = ['para', 'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos',
                     'com', 'por', 'sem', 'sob', 'sobre', 'ante', 'após', 'até', 'desde',
                     'e', 'ou', 'mas', 'que', 'se', 'o', 'a', 'os', 'as', 'um', 'uma'];

        $words = preg_split('/\s+/', strtolower($query));
        $words = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });

        return array_values($words);
    }

    private function calculatePartialPhraseScore(string $query, string $content): float
    {
        $queryWords = explode(' ', $query);
        $matches = 0;
        $totalWords = count($queryWords);

        foreach ($queryWords as $word) {
            if (strpos($content, $word) !== false) {
                $matches++;
            }
        }

        return $totalWords > 0 ? $matches / $totalWords : 0;
    }

    private function calculateWordScore(array $queryWords, string $content, string $originalContent): float
    {
        $score = 0;
        $totalWords = count($queryWords);

        if ($totalWords === 0) return 0;

        foreach ($queryWords as $word) {
            if (strpos($content, $word) !== false) {
                // Base score for word presence
                $wordScore = 1.0;

                // Bonus for word at beginning of sentences
                if (preg_match('/(?:^|[.!?]\s+)' . preg_quote($word, '/') . '/i', $originalContent)) {
                    $wordScore += 0.3;
                }

                // Bonus for numbered items
                if (preg_match('/^\s*\d+\.\s*.*' . preg_quote($word, '/') . '/mi', $originalContent)) {
                    $wordScore += 0.2;
                }

                $score += $wordScore;
            }
        }

        return $score / $totalWords;
    }

    private function calculateFuzzyScore(string $query, string $content): float
    {
        // Special handling for "motivos" queries
        if (preg_match('/(\d+)\s*motivos?/i', $query, $matches)) {
            $number = $matches[1];

            // Look for the number in content
            if (preg_match('/' . $number . '\s*motivos?/i', $content)) {
                return 0.95;
            }

            // Look for numbered list with that number
            if (preg_match('/^\s*' . $number . '\.\s*/m', $content)) {
                return 0.8;
            }
        }

        return 0;
    }

    private function calculateProximityScore(array $queryWords, string $content): float
    {
        if (count($queryWords) < 2) return 0;

        $positions = [];
        foreach ($queryWords as $word) {
            $pos = strpos($content, $word);
            if ($pos !== false) {
                $positions[$word] = $pos;
            }
        }

        if (count($positions) < 2) return 0;

        // Calculate average distance between words
        $distances = [];
        $positionValues = array_values($positions);
        for ($i = 0; $i < count($positionValues) - 1; $i++) {
            $distances[] = abs($positionValues[$i + 1] - $positionValues[$i]);
        }

        $avgDistance = array_sum($distances) / count($distances);

        // Closer words get higher score (inverse relationship)
        return max(0, 1 - ($avgDistance / 1000)); // Normalize by content length
    }
}