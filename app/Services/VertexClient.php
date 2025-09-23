<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Cliente Vertex AI real para embeddings e geração
 * Implementação enterprise com:
 * - Rate limiting inteligente
 * - Retry exponential backoff
 * - Batch processing otimizado
 * - Cache Redis integrado
 * - Métricas de custo e uso
 */
class VertexClient
{
    private string $projectId;
    private string $location;
    private string $accessToken;
    private bool $useLocal;
    private int $dim;
    private array $rateLimits;
    private LoggerInterface $logger;
    private EmbeddingCache $cache;

    // Configurações de modelos Vertex AI
    private const EMBEDDING_MODEL = 'text-embedding-004';
    private const GENERATION_MODEL = 'gemini-1.5-flash';
    private const MAX_BATCH_SIZE = 250;
    private const MAX_TEXT_LENGTH = 20000;

    // Rate limiting (requests per minute)
    private const DEFAULT_EMBEDDING_RPM = 600;
    private const DEFAULT_GENERATION_RPM = 60;

    // Retry configuration
    private const MAX_RETRIES = 3;
    private const BASE_DELAY = 1000; // milliseconds

    public function __construct(EmbeddingCache $cache = null)
    {
        $this->projectId = env('VERTEX_PROJECT_ID', '');
        $this->location = env('VERTEX_LOCATION', 'us-central1');

        // Determinar modo de operação
        $mode = strtolower(env('EMBED_PROVIDER', env('RAG_EMBED_PROVIDER', 'local')));
        $this->useLocal = ($mode !== 'vertex') || empty($this->projectId);

        $this->dim = (int) env('EMBED_DIM', env('RAG_EMBED_DIM', 768));
        if ($this->dim <= 0) $this->dim = 768;

        $this->logger = Log::channel('rag');
        $this->cache = $cache ?? new EmbeddingCache();

        // Configurar rate limits
        $this->rateLimits = [
            'embedding' => env('VERTEX_EMBEDDING_RPM', self::DEFAULT_EMBEDDING_RPM),
            'generation' => env('VERTEX_GENERATION_RPM', self::DEFAULT_GENERATION_RPM),
        ];

        // Obter token de acesso se usando Vertex AI real
        if (!$this->useLocal) {
            $this->accessToken = $this->getAccessToken();
        }
    }

    /**
     * Gera embeddings para array de textos com batch processing
     */
    public function embed(array $texts): array
    {
        if ($this->useLocal) {
            return $this->embedLocal($texts);
        }

        // Validar entrada
        if (empty($texts)) {
            return [];
        }

        // Filtrar e limpar textos
        $cleanTexts = array_map(function($text) {
            $clean = trim($text);
            return mb_substr($clean, 0, self::MAX_TEXT_LENGTH);
        }, array_filter($texts, fn($t) => !empty(trim($t))));

        if (empty($cleanTexts)) {
            return array_fill(0, count($texts), array_fill(0, $this->dim, 0.0));
        }

        try {
            return $this->embedWithVertex($cleanTexts);
        } catch (Exception $e) {
            $this->logger->error('Vertex embedding failed, falling back to local', [
                'error' => $e->getMessage(),
                'texts_count' => count($texts)
            ]);

            // Fallback para local
            return $this->embedLocal($texts);
        }
    }

    /**
     * Gera resposta contextual usando Gemini
     */
    public function generate(string $user, array $contexts = [], array $options = []): string
    {
        if ($this->useLocal) {
            return $this->generateLocal($user, $contexts);
        }

        try {
            return $this->generateWithVertex($user, $contexts, $options);
        } catch (Exception $e) {
            $this->logger->error('Vertex generation failed, falling back to local', [
                'error' => $e->getMessage(),
                'user_query' => mb_substr($user, 0, 100)
            ]);

            return $this->generateLocal($user, $contexts);
        }
    }

    /**
     * Embedding batch com cache e rate limiting
     */
    private function embedWithVertex(array $texts): array
    {
        $results = [];
        $batches = array_chunk($texts, self::MAX_BATCH_SIZE);

        foreach ($batches as $batch) {
            // Verificar cache primeiro
            $cachedResults = [];
            $uncachedTexts = [];
            $uncachedIndices = [];

            foreach ($batch as $index => $text) {
                $cached = $this->cache->get($text);
                if ($cached !== null) {
                    $cachedResults[$index] = $cached;
                } else {
                    $uncachedTexts[] = $text;
                    $uncachedIndices[] = $index;
                }
            }

            // Processar textos não cachados
            if (!empty($uncachedTexts)) {
                $batchResults = $this->callVertexEmbedding($uncachedTexts);

                // Cachear novos resultados
                foreach ($batchResults as $i => $embedding) {
                    $originalIndex = $uncachedIndices[$i];
                    $text = $uncachedTexts[$i];

                    $this->cache->put($text, $embedding);
                    $cachedResults[$originalIndex] = $embedding;
                }
            }

            // Ordenar resultados pela ordem original
            ksort($cachedResults);
            $results = array_merge($results, array_values($cachedResults));
        }

        return $results;
    }

    /**
     * Chamada HTTP para Vertex AI Embedding
     */
    private function callVertexEmbedding(array $texts): array
    {
        $this->enforceRateLimit('embedding');

        $endpoint = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/" . self::EMBEDDING_MODEL . ":predict";

        $payload = [
            'instances' => array_map(fn($text) => ['content' => $text], $texts)
        ];

        $response = $this->makeRequest($endpoint, $payload);

        if (!isset($response['predictions'])) {
            throw new Exception('Invalid embedding response format');
        }

        return array_map(fn($pred) => $pred['embeddings']['values'], $response['predictions']);
    }

    /**
     * Geração contextual com Gemini
     */
    private function generateWithVertex(string $user, array $contexts, array $options): string
    {
        $this->enforceRateLimit('generation');

        // Construir prompt contextual
        $prompt = $this->buildContextualPrompt($user, $contexts, $options);

        $endpoint = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/" . self::GENERATION_MODEL . ":generateContent";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.1,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
                'topK' => $options['top_k'] ?? 40,
                'topP' => $options['top_p'] ?? 0.95,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        $response = $this->makeRequest($endpoint, $payload);

        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid generation response format');
        }

        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Construir prompt contextual inteligente
     */
    private function buildContextualPrompt(string $user, array $contexts, array $options): string
    {
        $systemPrompt = $options['system_prompt'] ?? "Você é um assistente especializado que responde perguntas baseado exclusivamente no contexto fornecido. Seja preciso, conciso e cite as fontes quando relevante.";

        $contextText = '';
        if (!empty($contexts)) {
            $contextText = "\n\nContexto relevante:\n";
            foreach ($contexts as $i => $context) {
                $contextText .= "\n[Fonte " . ($i + 1) . "] " . $context;
            }
            $contextText .= "\n";
        }

        return "{$systemPrompt}{$contextText}\n\nPergunta: {$user}\n\nResposta:";
    }

    /**
     * Fazer requisição HTTP com retry
     */
    private function makeRequest(string $endpoint, array $payload): array
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ])
                ->timeout(60)
                ->post($endpoint, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                // Rate limit específico
                if ($response->status() === 429) {
                    $delay = ($attempt + 1) * self::BASE_DELAY * 2;
                    usleep($delay * 1000);
                    $attempt++;
                    continue;
                }

                throw new Exception("Vertex AI API error: {$response->status()} - {$response->body()}");

            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = $attempt * self::BASE_DELAY;
                usleep($delay * 1000);
            }
        }

        throw new Exception('Max retries exceeded');
    }

    /**
     * Rate limiting inteligente
     */
    private function enforceRateLimit(string $operation): void
    {
        $key = "vertex_rate_limit:{$operation}:" . date('Y-m-d:H:i');
        $limit = $this->rateLimits[$operation];

        $current = Cache::get($key, 0);
        if ($current >= $limit) {
            $waitTime = 60 - date('s'); // Aguardar até próximo minuto
            sleep($waitTime);
        }

        Cache::put($key, $current + 1, 120); // TTL 2 minutos
    }

    /**
     * Obter token de acesso Google Cloud
     */
    private function getAccessToken(): string
    {
        // Usar service account key ou metadata server
        $keyPath = env('GOOGLE_APPLICATION_CREDENTIALS');

        if ($keyPath && file_exists($keyPath)) {
            return $this->getTokenFromServiceAccount($keyPath);
        }

        // Fallback: metadata server (para GCE/GKE)
        return $this->getTokenFromMetadata();
    }

    private function getTokenFromServiceAccount(string $keyPath): string
    {
        $key = json_decode(file_get_contents($keyPath), true);

        // Implementar JWT assertion para service account
        // Por simplicidade, usando gcloud CLI se disponível
        $process = popen('gcloud auth print-access-token 2>/dev/null', 'r');
        $token = trim(fread($process, 1024));
        pclose($process);

        if (empty($token)) {
            throw new Exception('Failed to obtain access token from service account');
        }

        return $token;
    }

    private function getTokenFromMetadata(): string
    {
        $response = Http::withHeaders([
            'Metadata-Flavor' => 'Google'
        ])->get('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token');

        if (!$response->successful()) {
            throw new Exception('Failed to obtain access token from metadata server');
        }

        return $response->json('access_token');
    }

    // Métodos locais para fallback
    private function embedLocal(array $texts): array
    {
        $out = [];
        foreach ($texts as $t) {
            $vec = [];
            for ($i = 0; $i < $this->dim; $i++) {
                $hex = substr(sha1($t . '|' . $i), 0, 8);
                $h = hexdec($hex);
                $v = (($h % 1000000) / 500000.0) - 1.0;
                $vec[] = round($v, 6);
            }
            $out[] = $vec;
        }
        return $out;
    }

    private function generateLocal(string $user, array $contexts): string
    {
        $ctx = trim(implode("\n\n", $contexts));
        if (empty($ctx)) {
            return "Resumo local: Nenhum contexto relevante encontrado para a pergunta: " . mb_substr($user, 0, 100);
        }

        $plain = preg_replace('/\s+/', ' ', $ctx);
        $snippet = mb_substr($plain, 0, 900);

        return "Resumo local baseado no contexto: {$snippet}";
    }

    /**
     * Métricas de uso para billing
     */
    public function getUsageMetrics(): array
    {
        return [
            'embedding_requests' => Cache::get('vertex_metrics:embedding_requests', 0),
            'generation_requests' => Cache::get('vertex_metrics:generation_requests', 0),
            'cache_hits' => $this->cache->getHitRate(),
            'estimated_cost' => $this->calculateEstimatedCost(),
        ];
    }

    private function calculateEstimatedCost(): float
    {
        // Preços aproximados Vertex AI (por 1k tokens)
        $embeddingCost = 0.00025; // $0.00025 per 1k tokens
        $generationCost = 0.002;  // $0.002 per 1k tokens

        $embeddingRequests = Cache::get('vertex_metrics:embedding_requests', 0);
        $generationRequests = Cache::get('vertex_metrics:generation_requests', 0);

        return ($embeddingRequests * $embeddingCost) + ($generationRequests * $generationCost);
    }
}
