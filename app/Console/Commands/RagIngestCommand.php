<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RagIngestCommand extends Command
{
    protected $signature = 'rag:ingest 
                            {file : Caminho do arquivo para ingerir}
                            {--tenant=default : Tenant slug}
                            {--title= : Título personalizado (opcional)}
                            {--chunk-size=1000 : Tamanho máximo de cada chunk}
                            {--overlap=200 : Sobreposição entre chunks}';

    protected $description = 'Ingere um documento no sistema RAG';

    public function handle()
    {
        $filePath = $this->argument('file');
        $tenant = $this->option('tenant');
        $title = $this->option('title');
        $chunkSize = (int) $this->option('chunk-size');
        $overlap = (int) $this->option('overlap');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return 1;
        }

        $this->info("Iniciando ingestão de: {$filePath}");

        try {
            // Extrai texto do arquivo
            $text = $this->extractText($filePath);
            
            if (empty(trim($text))) {
                $this->error("Não foi possível extrair texto do arquivo");
                return 1;
            }

            // Define título
            if (!$title) {
                $title = pathinfo($filePath, PATHINFO_FILENAME);
            }

            $this->info("Texto extraído: " . strlen($text) . " caracteres");

            // Cria documento
            $documentId = DB::table('documents')->insertGetId([
                'tenant_slug' => $tenant,
                'title' => $title,
                'source' => 'file',
                'filename' => basename($filePath),
                'meta' => json_encode([
                    'file_size' => filesize($filePath),
                    'mime_type' => mime_content_type($filePath),
                    'ingested_at' => now()->toISOString(),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("Documento criado com ID: {$documentId}");

            // Cria chunks
            $chunks = $this->chunkText($text, $chunkSize, $overlap);
            
            $this->info("Criando " . count($chunks) . " chunks...");
            
            $bar = $this->output->createProgressBar(count($chunks));
            $bar->start();

            foreach ($chunks as $index => $chunk) {
                // Gera embedding mock (substituir por embedding real posteriormente)
                $embedding = $this->generateMockEmbedding();

                DB::table('chunks')->insert([
                    'document_id' => $documentId,
                    'ord' => $index,
                    'content' => $chunk,
                    'embedding' => json_encode($embedding),
                    'meta' => json_encode([
                        'length' => strlen($chunk),
                        'words' => str_word_count($chunk),
                        'chunk_index' => $index,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            
            $this->info("✅ Ingestão concluída!");
            $this->table(['Campo', 'Valor'], [
                ['Documento ID', $documentId],
                ['Título', $title],
                ['Chunks criados', count($chunks)],
                ['Tamanho do texto', number_format(strlen($text)) . ' chars'],
                ['Tenant', $tenant],
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Erro na ingestão: " . $e->getMessage());
            return 1;
        }
    }

    private function extractText(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'txt':
                return file_get_contents($filePath);
                
            case 'pdf':
                return $this->extractPdfText($filePath);
                
            case 'json':
                $data = json_decode(file_get_contents($filePath), true);
                return json_encode($data, JSON_PRETTY_PRINT);
                
            default:
                // Tenta ler como texto simples
                $content = file_get_contents($filePath);
                if (mb_check_encoding($content, 'UTF-8')) {
                    return $content;
                }
                
                throw new \Exception("Formato de arquivo não suportado: {$extension}");
        }
    }

    private function extractPdfText(string $filePath): string
    {
        // Tentativas diferentes de extração de PDF
        
        // Método 1: pdftotext (se disponível)
        if (exec('which pdftotext')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_extract');
            exec("pdftotext '{$filePath}' '{$tempFile}'", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempFile)) {
                $text = file_get_contents($tempFile);
                unlink($tempFile);
                if (!empty(trim($text))) {
                    return $text;
                }
            }
        }

        // Método 2: Simple PDF parser (básico)
        $content = file_get_contents($filePath);
        
        // Extração muito básica de PDF - procura por text streams
        if (preg_match_all('/BT\s+.*?ET/s', $content, $matches)) {
            $text = '';
            foreach ($matches[0] as $match) {
                // Extrai texto entre parênteses ou colchetes
                if (preg_match_all('/\[(.*?)\]|\((.*?)\)/', $match, $textMatches)) {
                    foreach ($textMatches[1] as $t1) {
                        $text .= $t1 . ' ';
                    }
                    foreach ($textMatches[2] as $t2) {
                        $text .= $t2 . ' ';
                    }
                }
            }
            
            if (!empty(trim($text))) {
                return trim($text);
            }
        }

        throw new \Exception("Não foi possível extrair texto do PDF. Instale pdftotext ou use outro formato.");
    }

    private function chunkText(string $text, int $maxSize, int $overlap): array
    {
        // Divide em sentenças primeiro
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $chunks = [];
        $currentChunk = '';
        $currentSize = 0;
        
        foreach ($sentences as $sentence) {
            $sentenceSize = strlen($sentence);
            
            // Se adicionar esta sentença ultrapassar o limite
            if ($currentSize + $sentenceSize > $maxSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                
                // Inicia novo chunk com overlap
                $overlapText = $this->getOverlapText($currentChunk, $overlap);
                $currentChunk = $overlapText . ' ' . $sentence;
                $currentSize = strlen($currentChunk);
            } else {
                $currentChunk .= ' ' . $sentence;
                $currentSize += $sentenceSize + 1;
            }
        }
        
        // Adiciona o último chunk se não estiver vazio
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }
        
        return array_filter($chunks, fn($chunk) => strlen(trim($chunk)) > 10);
    }

    private function getOverlapText(string $text, int $overlapSize): string
    {
        if (strlen($text) <= $overlapSize) {
            return $text;
        }
        
        // Pega os últimos N caracteres, tentando não quebrar palavras
        $overlap = substr($text, -$overlapSize);
        $firstSpace = strpos($overlap, ' ');
        
        if ($firstSpace !== false && $firstSpace < $overlapSize / 2) {
            return substr($overlap, $firstSpace + 1);
        }
        
        return $overlap;
    }

    private function generateMockEmbedding(): array
    {
        // Gera embedding mock de 768 dimensões
        // TODO: Substituir por embedding real (OpenAI, Vertex, etc.)
        $embedding = [];
        for ($i = 0; $i < 768; $i++) {
            $embedding[] = (rand(-1000, 1000) / 1000.0);
        }
        return $embedding;
    }
}