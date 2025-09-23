<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Cache Redis especializado para embeddings
 *
 * Funcionalidades:
 * - Cache por hash SHA256 do texto
 * - TTL configurável (30 dias default)
 * - Métricas de hit/miss rate
 * - Compressão opcional para economia de espaço
 * - Limpeza automática de cache antigo
 * - Namespace por tenant para isolamento
 */
class EmbeddingCache
{
    private string $prefix;
    private int $defaultTtl;
    private bool $compressionEnabled;
    private array $metrics;

    // Configurações de cache
    private const DEFAULT_TTL = 2592000; // 30 dias
    private const COMPRESSION_THRESHOLD = 1024; // Comprimir acima de 1KB
    private const METRICS_KEY = 'embedding_cache_metrics';
    private const CLEANUP_INTERVAL = 86400; // Limpeza diária

    public function __construct(string $tenantSlug = null)
    {
        $this->prefix = 'emb_cache:' . ($tenantSlug ?? 'global') . ':';
        $this->defaultTtl = (int) env('EMBEDDING_CACHE_TTL', self::DEFAULT_TTL);
        $this->compressionEnabled = env('EMBEDDING_CACHE_COMPRESSION', true);

        $this->initializeMetrics();
        $this->scheduleCleanup();
    }

    /**
     * Armazenar embedding no cache
     */
    public function put(string $text, array $embedding, int $ttl = null): bool
    {
        try {
            $key = $this->generateKey($text);
            $ttl = $ttl ?? $this->defaultTtl;

            // Preparar dados para cache
            $data = [
                'embedding' => $embedding,
                'dimensions' => count($embedding),
                'text_hash' => $this->textHash($text),
                'text_length' => mb_strlen($text),
                'cached_at' => Carbon::now()->toISOString(),
                'access_count' => 1,
            ];

            $serialized = serialize($data);

            // Comprimir se necessário
            if ($this->compressionEnabled && strlen($serialized) > self::COMPRESSION_THRESHOLD) {
                $compressed = gzcompress($serialized, 6);
                if ($compressed !== false && strlen($compressed) < strlen($serialized)) {
                    $serialized = $compressed;
                    $data['compressed'] = true;
                }
            }

            // Armazenar no Redis
            $result = Redis::setex($key, $ttl, $serialized);

            if ($result) {
                $this->incrementMetric('puts');
                $this->incrementMetric('total_embeddings');
                Log::debug('Embedding cached', [
                    'key' => $key,
                    'dimensions' => count($embedding),
                    'ttl' => $ttl
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to cache embedding', [
                'error' => $e->getMessage(),
                'text_length' => mb_strlen($text)
            ]);
            return false;
        }
    }

    /**
     * Recuperar embedding do cache
     */
    public function get(string $text): ?array
    {
        try {
            $key = $this->generateKey($text);
            $cached = Redis::get($key);

            if ($cached === null) {
                $this->incrementMetric('misses');
                return null;
            }

            // Tentar descomprimir se necessário
            $data = null;

            // Primeiro tentar como dados comprimidos
            if (strlen($cached) > 10) {
                $decompressed = @gzuncompress($cached);
                if ($decompressed !== false) {
                    $data = @unserialize($decompressed);
                }
            }

            // Se não funcionou, tentar como dados não comprimidos
            if ($data === null || $data === false) {
                $data = @unserialize($cached);
            }

            if (!$data || !isset($data['embedding'])) {
                $this->incrementMetric('misses');
                return null;
            }

            // Validar integridade
            $currentHash = $this->textHash($text);
            if (isset($data['text_hash']) && $data['text_hash'] !== $currentHash) {
                // Hash diferente = texto mudou, invalidar cache
                Redis::del($key);
                $this->incrementMetric('invalidations');
                return null;
            }

            // Atualizar contador de acesso
            $data['access_count'] = ($data['access_count'] ?? 0) + 1;
            $data['last_accessed'] = Carbon::now()->toISOString();

            // Re-cache com dados atualizados (sem alterar TTL)
            $serialized = serialize($data);
            if ($this->compressionEnabled && strlen($serialized) > self::COMPRESSION_THRESHOLD) {
                $compressed = gzcompress($serialized, 6);
                if ($compressed !== false && strlen($compressed) < strlen($serialized)) {
                    $serialized = $compressed;
                }
            }

            Redis::set($key, $serialized, 'KEEPTTL');

            $this->incrementMetric('hits');

            Log::debug('Embedding cache hit', [
                'key' => $key,
                'dimensions' => count($data['embedding']),
                'access_count' => $data['access_count']
            ]);

            return $data['embedding'];

        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached embedding', [
                'error' => $e->getMessage(),
                'text_length' => mb_strlen($text)
            ]);
            $this->incrementMetric('errors');
            return null;
        }
    }

    /**
     * Verificar se texto está no cache
     */
    public function has(string $text): bool
    {
        $key = $this->generateKey($text);
        return Redis::exists($key) > 0;
    }

    /**
     * Remover embedding do cache
     */
    public function forget(string $text): bool
    {
        $key = $this->generateKey($text);
        $result = Redis::del($key) > 0;

        if ($result) {
            $this->incrementMetric('deletions');
        }

        return $result;
    }

    /**
     * Limpar todo o cache do tenant atual
     */
    public function flush(): int
    {
        $pattern = $this->prefix . '*';
        $keys = Redis::keys($pattern);

        if (empty($keys)) {
            return 0;
        }

        $deleted = Redis::del($keys);
        $this->incrementMetric('flushes');

        Log::info('Embedding cache flushed', [
            'tenant_prefix' => $this->prefix,
            'keys_deleted' => $deleted
        ]);

        return $deleted;
    }

    /**
     * Obter estatísticas do cache
     */
    public function getStats(): array
    {
        $metrics = $this->getMetrics();
        $hitRate = $this->getHitRate();

        // Informações do Redis
        $info = Redis::info('memory');
        $usedMemory = $info['used_memory'] ?? 0;
        $usedMemoryHuman = $info['used_memory_human'] ?? '0B';

        // Contagem de chaves do tenant
        $pattern = $this->prefix . '*';
        $keyCount = count(Redis::keys($pattern));

        return [
            'hit_rate' => $hitRate,
            'total_requests' => $metrics['hits'] + $metrics['misses'],
            'hits' => $metrics['hits'],
            'misses' => $metrics['misses'],
            'puts' => $metrics['puts'],
            'deletions' => $metrics['deletions'],
            'errors' => $metrics['errors'],
            'invalidations' => $metrics['invalidations'],
            'total_embeddings' => $metrics['total_embeddings'],
            'key_count' => $keyCount,
            'redis_memory_used' => $usedMemory,
            'redis_memory_human' => $usedMemoryHuman,
            'compression_enabled' => $this->compressionEnabled,
            'default_ttl' => $this->defaultTtl,
            'tenant_prefix' => $this->prefix,
        ];
    }

    /**
     * Taxa de acerto do cache (0-100%)
     */
    public function getHitRate(): float
    {
        $metrics = $this->getMetrics();
        $total = $metrics['hits'] + $metrics['misses'];

        if ($total === 0) {
            return 0.0;
        }

        return round(($metrics['hits'] / $total) * 100, 2);
    }

    /**
     * Limpeza automática de entradas expiradas
     */
    public function cleanup(): int
    {
        $pattern = $this->prefix . '*';
        $keys = Redis::keys($pattern);
        $cleaned = 0;

        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);

            // Remover chaves que expiraram ou com TTL inválido
            if ($ttl === -1 || $ttl === -2) {
                Redis::del($key);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Log::info('Embedding cache cleanup completed', [
                'keys_cleaned' => $cleaned,
                'tenant_prefix' => $this->prefix
            ]);
            $this->incrementMetric('cleanups');
        }

        return $cleaned;
    }

    /**
     * Análise de uso detalhada
     */
    public function getUsageAnalysis(): array
    {
        $pattern = $this->prefix . '*';
        $keys = Redis::keys($pattern);
        $analysis = [
            'total_keys' => count($keys),
            'size_distribution' => [],
            'access_patterns' => [],
            'ttl_distribution' => [],
        ];

        $sampleSize = min(100, count($keys)); // Analisar amostra
        $sampleKeys = array_slice($keys, 0, $sampleSize);

        foreach ($sampleKeys as $key) {
            try {
                $data = Redis::get($key);
                if ($data) {
                    $size = strlen($data);
                    $ttl = Redis::ttl($key);

                    // Distribuição de tamanho
                    $sizeRange = $this->getSizeRange($size);
                    $analysis['size_distribution'][$sizeRange] =
                        ($analysis['size_distribution'][$sizeRange] ?? 0) + 1;

                    // Distribuição de TTL
                    $ttlRange = $this->getTtlRange($ttl);
                    $analysis['ttl_distribution'][$ttlRange] =
                        ($analysis['ttl_distribution'][$ttlRange] ?? 0) + 1;
                }
            } catch (\Exception $e) {
                // Ignorar chaves com problemas
                continue;
            }
        }

        return $analysis;
    }

    // Métodos privados

    private function generateKey(string $text): string
    {
        return $this->prefix . hash('sha256', trim($text));
    }

    private function textHash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    private function initializeMetrics(): void
    {
        $this->metrics = [
            'hits' => 0,
            'misses' => 0,
            'puts' => 0,
            'deletions' => 0,
            'errors' => 0,
            'invalidations' => 0,
            'flushes' => 0,
            'cleanups' => 0,
            'total_embeddings' => 0,
        ];
    }

    private function getMetrics(): array
    {
        $key = self::METRICS_KEY . ':' . $this->prefix;
        $cached = Redis::get($key);

        if ($cached) {
            return array_merge($this->metrics, json_decode($cached, true) ?: []);
        }

        return $this->metrics;
    }

    private function incrementMetric(string $metric): void
    {
        $key = self::METRICS_KEY . ':' . $this->prefix;
        $metrics = $this->getMetrics();
        $metrics[$metric] = ($metrics[$metric] ?? 0) + 1;

        Redis::setex($key, 86400 * 7, json_encode($metrics)); // 7 dias TTL para métricas
    }

    private function scheduleCleanup(): void
    {
        $lastCleanup = Redis::get('last_cleanup:' . $this->prefix);
        $now = time();

        if (!$lastCleanup || ($now - (int)$lastCleanup) > self::CLEANUP_INTERVAL) {
            // Agendar limpeza assíncrona
            dispatch(function() {
                $this->cleanup();
                Redis::set('last_cleanup:' . $this->prefix, time());
            });
        }
    }

    private function getSizeRange(int $size): string
    {
        if ($size < 1024) return '<1KB';
        if ($size < 10240) return '1-10KB';
        if ($size < 51200) return '10-50KB';
        if ($size < 102400) return '50-100KB';
        return '>100KB';
    }

    private function getTtlRange(int $ttl): string
    {
        if ($ttl < 0) return 'No TTL';
        if ($ttl < 3600) return '<1h';
        if ($ttl < 86400) return '1-24h';
        if ($ttl < 604800) return '1-7d';
        return '>7d';
    }
}