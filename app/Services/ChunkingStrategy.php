<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Estratégias de chunking inteligente baseadas no tipo de documento
 *
 * Implementa diferentes estratégias:
 * - PDF: por seções/páginas + overlap semântico
 * - Office: por parágrafos + preservar tabelas
 * - HTML: por elementos + hierarquia
 * - Texto: por sentença + janela deslizante
 * - Configuração personalizada por tenant
 */
class ChunkingStrategy
{
    // Configurações padrão por tipo
    private const DEFAULT_CONFIGS = [
        'pdf' => [
            'chunk_size' => 1000,
            'overlap_size' => 150,
            'respect_page_breaks' => true,
            'preserve_tables' => true,
            'section_aware' => true,
        ],
        'docx' => [
            'chunk_size' => 800,
            'overlap_size' => 120,
            'respect_paragraphs' => true,
            'preserve_tables' => true,
            'preserve_lists' => true,
        ],
        'xlsx' => [
            'chunk_size' => 500,
            'overlap_size' => 50,
            'chunk_by_sheet' => true,
            'preserve_headers' => true,
            'include_formulas' => false,
        ],
        'pptx' => [
            'chunk_size' => 600,
            'overlap_size' => 80,
            'chunk_by_slide' => true,
            'include_speaker_notes' => true,
            'preserve_bullet_structure' => true,
        ],
        'html' => [
            'chunk_size' => 900,
            'overlap_size' => 100,
            'respect_dom_structure' => true,
            'preserve_links' => true,
            'semantic_sections' => true,
        ],
        'txt' => [
            'chunk_size' => 1000,
            'overlap_size' => 150,
            'sentence_boundary' => true,
            'paragraph_aware' => true,
            'sliding_window' => true,
        ],
        'csv' => [
            'chunk_size' => 300,
            'overlap_size' => 30,
            'preserve_headers' => true,
            'group_by_rows' => true,
            'max_rows_per_chunk' => 20,
        ],
    ];

    private array $tenantConfigs = [];

    public function __construct()
    {
        $this->loadTenantConfigurations();
    }

    /**
     * Chunk documento baseado no tipo e configurações
     */
    public function chunk(string $content, string $documentType, array $options = []): array
    {
        $config = $this->getConfigForType($documentType, $options);

        Log::info('Starting document chunking', [
            'document_type' => $documentType,
            'content_length' => strlen($content),
            'chunk_size' => $config['chunk_size'],
            'overlap_size' => $config['overlap_size']
        ]);

        try {
            switch (strtolower($documentType)) {
                case 'pdf':
                    return $this->chunkPdf($content, $config, $options);
                case 'docx':
                case 'doc':
                    return $this->chunkDocx($content, $config, $options);
                case 'xlsx':
                case 'xls':
                    return $this->chunkXlsx($content, $config, $options);
                case 'pptx':
                case 'ppt':
                    return $this->chunkPptx($content, $config, $options);
                case 'html':
                case 'htm':
                    return $this->chunkHtml($content, $config, $options);
                case 'csv':
                    return $this->chunkCsv($content, $config, $options);
                case 'txt':
                case 'text':
                default:
                    return $this->chunkText($content, $config, $options);
            }
        } catch (\Exception $e) {
            Log::error('Chunking failed, falling back to basic text chunking', [
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);

            // Fallback para chunking básico
            return $this->chunkText($content, $config, $options);
        }
    }

    /**
     * Chunking para PDFs - por seções e páginas
     */
    private function chunkPdf(string $content, array $config, array $options): array
    {
        $chunks = [];

        // Detectar quebras de página (se disponível no texto extraído)
        if ($config['respect_page_breaks'] && strpos($content, '\f') !== false) {
            $pages = explode('\f', $content);

            foreach ($pages as $pageIndex => $pageContent) {
                $pageChunks = $this->chunkBySize(
                    $pageContent,
                    $config['chunk_size'],
                    $config['overlap_size']
                );

                foreach ($pageChunks as $chunkIndex => $chunk) {
                    $chunks[] = [
                        'content' => $chunk,
                        'metadata' => [
                            'page' => $pageIndex + 1,
                            'chunk_in_page' => $chunkIndex + 1,
                            'type' => 'pdf_chunk'
                        ]
                    ];
                }
            }
        } else {
            // Chunking por seções semânticas
            if ($config['section_aware']) {
                $sections = $this->detectSections($content);
                foreach ($sections as $sectionIndex => $section) {
                    $sectionChunks = $this->chunkBySize(
                        $section['content'],
                        $config['chunk_size'],
                        $config['overlap_size']
                    );

                    foreach ($sectionChunks as $chunkIndex => $chunk) {
                        $chunks[] = [
                            'content' => $chunk,
                            'metadata' => [
                                'section' => $section['title'],
                                'section_index' => $sectionIndex,
                                'chunk_in_section' => $chunkIndex + 1,
                                'type' => 'pdf_section_chunk'
                            ]
                        ];
                    }
                }
            } else {
                // Chunking básico com overlap
                $basicChunks = $this->chunkBySize($content, $config['chunk_size'], $config['overlap_size']);
                foreach ($basicChunks as $index => $chunk) {
                    $chunks[] = [
                        'content' => $chunk,
                        'metadata' => [
                            'chunk_index' => $index,
                            'type' => 'pdf_basic_chunk'
                        ]
                    ];
                }
            }
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para documentos Word - por parágrafos
     */
    private function chunkDocx(string $content, array $config, array $options): array
    {
        $chunks = [];

        if ($config['respect_paragraphs']) {
            // Dividir por parágrafos (dupla quebra de linha)
            $paragraphs = preg_split('/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY);

            $currentChunk = '';
            $currentSize = 0;

            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (empty($paragraph)) continue;

                $paragraphSize = mb_strlen($paragraph);

                // Se parágrafo é muito grande, quebre ele
                if ($paragraphSize > $config['chunk_size']) {
                    // Salvar chunk atual se existe
                    if (!empty($currentChunk)) {
                        $chunks[] = [
                            'content' => $currentChunk,
                            'metadata' => ['type' => 'docx_paragraph_chunk']
                        ];
                        $currentChunk = '';
                        $currentSize = 0;
                    }

                    // Quebrar parágrafo grande
                    $subChunks = $this->chunkBySize($paragraph, $config['chunk_size'], $config['overlap_size']);
                    foreach ($subChunks as $subChunk) {
                        $chunks[] = [
                            'content' => $subChunk,
                            'metadata' => ['type' => 'docx_large_paragraph_chunk']
                        ];
                    }
                    continue;
                }

                // Verificar se cabe no chunk atual
                if ($currentSize + $paragraphSize + 2 <= $config['chunk_size']) {
                    $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
                    $currentSize += $paragraphSize + 2;
                } else {
                    // Salvar chunk atual e começar novo
                    if (!empty($currentChunk)) {
                        $chunks[] = [
                            'content' => $currentChunk,
                            'metadata' => ['type' => 'docx_paragraph_chunk']
                        ];
                    }
                    $currentChunk = $paragraph;
                    $currentSize = $paragraphSize;
                }
            }

            // Adicionar último chunk
            if (!empty($currentChunk)) {
                $chunks[] = [
                    'content' => $currentChunk,
                    'metadata' => ['type' => 'docx_paragraph_chunk']
                ];
            }
        } else {
            // Chunking básico
            $basicChunks = $this->chunkBySize($content, $config['chunk_size'], $config['overlap_size']);
            foreach ($basicChunks as $index => $chunk) {
                $chunks[] = [
                    'content' => $chunk,
                    'metadata' => ['type' => 'docx_basic_chunk', 'chunk_index' => $index]
                ];
            }
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para planilhas Excel - por folhas e grupos de linhas
     */
    private function chunkXlsx(string $content, array $config, array $options): array
    {
        $chunks = [];

        if ($config['chunk_by_sheet'] && strpos($content, '=== Sheet:') !== false) {
            // Dividir por folhas
            $sheets = preg_split('/=== Sheet: (.+?) ===/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 1; $i < count($sheets); $i += 2) {
                $sheetName = $sheets[$i];
                $sheetContent = $sheets[$i + 1] ?? '';

                if (empty(trim($sheetContent))) continue;

                $sheetChunks = $this->chunkTableData($sheetContent, $config);
                foreach ($sheetChunks as $chunk) {
                    $chunks[] = [
                        'content' => $chunk,
                        'metadata' => [
                            'sheet_name' => $sheetName,
                            'type' => 'xlsx_sheet_chunk'
                        ]
                    ];
                }
            }
        } else {
            // Chunking por grupos de linhas
            $chunks = $this->chunkTableData($content, $config);
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para apresentações PowerPoint - por slides
     */
    private function chunkPptx(string $content, array $config, array $options): array
    {
        $chunks = [];

        if ($config['chunk_by_slide']) {
            // Detectar slides por padrões típicos
            $slides = preg_split('/\n(?=Slide \d+:|\[Slide \d+\]|--- Slide)/', $content, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($slides as $slideIndex => $slideContent) {
                $slideContent = trim($slideContent);
                if (empty($slideContent)) continue;

                // Se slide é muito grande, quebre
                if (mb_strlen($slideContent) > $config['chunk_size']) {
                    $slideChunks = $this->chunkBySize($slideContent, $config['chunk_size'], $config['overlap_size']);
                    foreach ($slideChunks as $chunkIndex => $chunk) {
                        $chunks[] = [
                            'content' => $chunk,
                            'metadata' => [
                                'slide_number' => $slideIndex + 1,
                                'chunk_in_slide' => $chunkIndex + 1,
                                'type' => 'pptx_slide_chunk'
                            ]
                        ];
                    }
                } else {
                    $chunks[] = [
                        'content' => $slideContent,
                        'metadata' => [
                            'slide_number' => $slideIndex + 1,
                            'type' => 'pptx_slide_chunk'
                        ]
                    ];
                }
            }
        } else {
            // Chunking básico
            $basicChunks = $this->chunkBySize($content, $config['chunk_size'], $config['overlap_size']);
            foreach ($basicChunks as $index => $chunk) {
                $chunks[] = [
                    'content' => $chunk,
                    'metadata' => ['type' => 'pptx_basic_chunk', 'chunk_index' => $index]
                ];
            }
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para HTML - por elementos semânticos
     */
    private function chunkHtml(string $content, array $config, array $options): array
    {
        $chunks = [];

        if ($config['semantic_sections']) {
            // Detectar seções semânticas (h1, h2, etc.)
            $sections = $this->extractHtmlSections($content);

            foreach ($sections as $section) {
                if (mb_strlen($section['content']) > $config['chunk_size']) {
                    $sectionChunks = $this->chunkBySize($section['content'], $config['chunk_size'], $config['overlap_size']);
                    foreach ($sectionChunks as $chunk) {
                        $chunks[] = [
                            'content' => $chunk,
                            'metadata' => [
                                'section_title' => $section['title'],
                                'section_level' => $section['level'],
                                'type' => 'html_section_chunk'
                            ]
                        ];
                    }
                } else {
                    $chunks[] = [
                        'content' => $section['content'],
                        'metadata' => [
                            'section_title' => $section['title'],
                            'section_level' => $section['level'],
                            'type' => 'html_section_chunk'
                        ]
                    ];
                }
            }
        } else {
            // Chunking básico
            $basicChunks = $this->chunkBySize($content, $config['chunk_size'], $config['overlap_size']);
            foreach ($basicChunks as $index => $chunk) {
                $chunks[] = [
                    'content' => $chunk,
                    'metadata' => ['type' => 'html_basic_chunk', 'chunk_index' => $index]
                ];
            }
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para CSV - por grupos de linhas
     */
    private function chunkCsv(string $content, array $config, array $options): array
    {
        $chunks = [];
        $lines = explode("\n", $content);

        if (empty($lines)) {
            return $chunks;
        }

        $header = $config['preserve_headers'] && !empty($lines) ? $lines[0] : '';
        $dataLines = $config['preserve_headers'] ? array_slice($lines, 1) : $lines;

        $maxRowsPerChunk = $config['max_rows_per_chunk'] ?? 20;
        $rowBatches = array_chunk($dataLines, $maxRowsPerChunk);

        foreach ($rowBatches as $batchIndex => $batch) {
            $chunkContent = $config['preserve_headers'] && $header ? $header . "\n" : '';
            $chunkContent .= implode("\n", $batch);

            $chunks[] = [
                'content' => trim($chunkContent),
                'metadata' => [
                    'batch_index' => $batchIndex + 1,
                    'rows_count' => count($batch),
                    'has_header' => !empty($header),
                    'type' => 'csv_batch_chunk'
                ]
            ];
        }

        return $this->validateChunks($chunks, $config);
    }

    /**
     * Chunking para texto - por sentenças com janela deslizante
     */
    private function chunkText(string $content, array $config, array $options): array
    {
        if ($config['sentence_boundary']) {
            return $this->chunkBySentences($content, $config);
        }

        if ($config['sliding_window']) {
            return $this->chunkWithSlidingWindow($content, $config);
        }

        // Chunking básico por tamanho
        $basicChunks = $this->chunkBySize($content, $config['chunk_size'], $config['overlap_size']);
        $chunks = [];

        foreach ($basicChunks as $index => $chunk) {
            $chunks[] = [
                'content' => $chunk,
                'metadata' => ['type' => 'text_basic_chunk', 'chunk_index' => $index]
            ];
        }

        return $this->validateChunks($chunks, $config);
    }

    // Métodos auxiliares

    private function chunkBySize(string $text, int $chunkSize, int $overlapSize): array
    {
        $chunks = [];
        $textLength = mb_strlen($text);
        $position = 0;

        while ($position < $textLength) {
            $chunk = mb_substr($text, $position, $chunkSize);
            $chunks[] = trim($chunk);
            $position += $chunkSize - $overlapSize;
        }

        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    private function chunkBySentences(string $text, array $config): array
    {
        // Dividir por sentenças
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';
        $currentSize = 0;

        foreach ($sentences as $sentence) {
            $sentenceSize = mb_strlen($sentence);

            if ($currentSize + $sentenceSize <= $config['chunk_size']) {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                $currentSize += $sentenceSize + 1;
            } else {
                if (!empty($currentChunk)) {
                    $chunks[] = [
                        'content' => $currentChunk,
                        'metadata' => ['type' => 'text_sentence_chunk']
                    ];
                }
                $currentChunk = $sentence;
                $currentSize = $sentenceSize;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = [
                'content' => $currentChunk,
                'metadata' => ['type' => 'text_sentence_chunk']
            ];
        }

        return $chunks;
    }

    private function chunkWithSlidingWindow(string $text, array $config): array
    {
        $words = explode(' ', $text);
        $wordsPerChunk = (int)($config['chunk_size'] / 6); // Aproximadamente 6 chars por palavra
        $overlapWords = (int)($config['overlap_size'] / 6);

        $chunks = [];
        $position = 0;

        while ($position < count($words)) {
            $chunkWords = array_slice($words, $position, $wordsPerChunk);
            $chunkText = implode(' ', $chunkWords);

            $chunks[] = [
                'content' => $chunkText,
                'metadata' => ['type' => 'text_sliding_window_chunk']
            ];

            $position += $wordsPerChunk - $overlapWords;
        }

        return $chunks;
    }

    private function detectSections(string $content): array
    {
        // Detectar seções por padrões comuns
        $sections = [];
        $lines = explode("\n", $content);
        $currentSection = ['title' => 'Início', 'content' => ''];

        foreach ($lines as $line) {
            $line = trim($line);

            // Detectar títulos (MAIÚSCULA, números, etc.)
            if (preg_match('/^[A-Z\s\d]{5,50}$/', $line) ||
                preg_match('/^\d+[\.\)]\s+[A-Z]/', $line) ||
                preg_match('/^[A-Z][a-z\s]+:$/', $line)) {

                // Salvar seção anterior
                if (!empty($currentSection['content'])) {
                    $sections[] = $currentSection;
                }

                // Nova seção
                $currentSection = ['title' => $line, 'content' => ''];
            } else {
                $currentSection['content'] .= $line . "\n";
            }
        }

        // Adicionar última seção
        if (!empty($currentSection['content'])) {
            $sections[] = $currentSection;
        }

        return $sections;
    }

    private function extractHtmlSections(string $content): array
    {
        $sections = [];

        // Buscar por padrões de títulos HTML extraídos
        if (preg_match_all('/H(\d+): (.+?)(?=H\d+:|$)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sections[] = [
                    'level' => (int)$match[1],
                    'title' => trim($match[2]),
                    'content' => $match[0]
                ];
            }
        } else {
            // Fallback: dividir por parágrafos
            $paragraphs = preg_split('/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($paragraphs as $index => $paragraph) {
                $sections[] = [
                    'level' => 1,
                    'title' => 'Parágrafo ' . ($index + 1),
                    'content' => trim($paragraph)
                ];
            }
        }

        return $sections;
    }

    private function chunkTableData(string $content, array $config): array
    {
        $chunks = [];
        $lines = explode("\n", $content);
        $header = '';
        $dataLines = [];

        // Identificar cabeçalho (primeira linha com separadores)
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (empty($header) && (strpos($line, '|') !== false || strpos($line, ',') !== false)) {
                $header = $line;
            } else {
                $dataLines[] = $line;
            }
        }

        // Agrupar linhas em chunks
        $maxRows = $config['max_rows_per_chunk'] ?? 10;
        $rowGroups = array_chunk($dataLines, $maxRows);

        foreach ($rowGroups as $group) {
            $chunkContent = $config['preserve_headers'] && $header ? $header . "\n" : '';
            $chunkContent .= implode("\n", $group);

            $chunks[] = trim($chunkContent);
        }

        return $chunks;
    }

    private function validateChunks(array $chunks, array $config): array
    {
        $minSize = $config['min_chunk_size'] ?? 50;

        return array_values(array_filter($chunks, function($chunk) use ($minSize) {
            $content = is_array($chunk) ? $chunk['content'] : $chunk;
            return mb_strlen(trim($content)) >= $minSize;
        }));
    }

    private function getConfigForType(string $documentType, array $options): array
    {
        $baseConfig = self::DEFAULT_CONFIGS[strtolower($documentType)] ?? self::DEFAULT_CONFIGS['txt'];

        // Aplicar configurações do tenant se existir
        $tenantSlug = $options['tenant_slug'] ?? null;
        if ($tenantSlug && isset($this->tenantConfigs[$tenantSlug][$documentType])) {
            $baseConfig = array_merge($baseConfig, $this->tenantConfigs[$tenantSlug][$documentType]);
        }

        // Aplicar opções específicas da chamada
        return array_merge($baseConfig, $options);
    }

    private function loadTenantConfigurations(): void
    {
        // Carregar configurações customizadas por tenant
        // Por enquanto vazio, pode ser implementado via DB ou cache
        $this->tenantConfigs = [];
    }

    /**
     * Definir configuração customizada para tenant
     */
    public function setTenantConfig(string $tenantSlug, string $documentType, array $config): void
    {
        $this->tenantConfigs[$tenantSlug][$documentType] = $config;
    }

    /**
     * Obter estatísticas de chunking
     */
    public function getStats(): array
    {
        return [
            'supported_types' => array_keys(self::DEFAULT_CONFIGS),
            'tenant_configs' => count($this->tenantConfigs),
            'default_chunk_sizes' => array_map(fn($config) => $config['chunk_size'], self::DEFAULT_CONFIGS)
        ];
    }
}