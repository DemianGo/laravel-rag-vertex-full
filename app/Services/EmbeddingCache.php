<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
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
    private bool $useRedis;
    private string $fallbackStore;

    // Configurações de cache
    private const DEFAULT_TTL = 2592000; // 30 dias
    private const COMPRESSION_THRESHOLD = 1024; // Comprimir acima de 1KB
    private const METRICS_KEY = 'embedding_cache_metrics';
    private const CLEANUP_INTERVAL = 86400; // Limpeza diária

    public function __construct(?string $tenantSlug = null)
    {
        $this->prefix = 'emb_cache:' . ($tenantSlug ?? 'global') . ':';
        $this->defaultTtl = (int) env('EMBEDDING_CACHE_TTL', self::DEFAULT_TTL);
        $this->compressionEnabled = env('EMBEDDING_CACHE_COMPRESSION', true);

        // Detectar se Redis está disponível e configurar fallback
        $this->useRedis = $this->isRedisAvailable();
        $this->fallbackStore = config('cache.default', 'file');

        if (!$this->useRedis) {
            Log::info('EmbeddingCache: Redis not available, using fallback store: ' . $this->fallbackStore);
        }

        $this->initializeMetrics();

        if ($this->useRedis) {
            $this->scheduleCleanup();
        }
    }

    /**
     * Armazenar embedding no cache
     */
    public function put(string $text, array $embedding, ?int $ttl = null): bool
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

            // Armazenar no cache (Redis ou fallback)
            if ($this->useRedis) {
                $result = Redis::setex($key, $ttl, $serialized);
            } else {
                $result = Cache::store($this->fallbackStore)->put($key, $serialized, $ttl);
            }

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

            // Recuperar do cache (Redis ou fallback)
            if ($this->useRedis) {
                $cached = Redis::get($key);
            } else {
                $cached = Cache::store($this->fallbackStore)->get($key);
            }

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
                if ($this->useRedis) {
                    Redis::del($key);
                } else {
                    Cache::store($this->fallbackStore)->forget($key);
                }
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

            // Re-armazenar (Redis com KEEPTTL ou fallback mantendo TTL original)
            if ($this->useRedis) {
                Redis::set($key, $serialized, 'KEEPTTL');
            } else {
                // Para fallback, não há KEEPTTL direto, então usamos o TTL original
                Cache::store($this->fallbackStore)->put($key, $serialized, $this->defaultTtl);
            }

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

        if ($this->useRedis) {
            return Redis::exists($key) > 0;
        } else {
            return Cache::store($this->fallbackStore)->has($key);
        }
    }

    /**
     * Remover embedding do cache
     */
    public function forget(string $text): bool
    {
        $key = $this->generateKey($text);

        if ($this->useRedis) {
            $result = Redis::del($key) > 0;
        } else {
            $result = Cache::store($this->fallbackStore)->forget($key);
        }

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
        if ($this->useRedis) {
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
        } else {
            // Para fallback stores, não temos pattern matching direto
            // Flush completo do store (pode afetar outros dados)
            Cache::store($this->fallbackStore)->flush();
            $this->incrementMetric('flushes');

            Log::info('Embedding cache flushed (fallback store)', [
                'tenant_prefix' => $this->prefix,
                'store' => $this->fallbackStore
            ]);

            return 1; // Retorna 1 para indicar sucesso
        }
    }

    /**
     * Obter estatísticas do cache
     */
    public function getStats(): array
    {
        $metrics = $this->getMetrics();
        $hitRate = $this->getHitRate();

        $stats = [
            'backend' => $this->useRedis ? 'redis' : 'fallback',
            'fallback_store' => $this->fallbackStore,
            'hit_rate' => $hitRate,
            'total_requests' => $metrics['hits'] + $metrics['misses'],
            'hits' => $metrics['hits'],
            'misses' => $metrics['misses'],
            'puts' => $metrics['puts'],
            'deletions' => $metrics['deletions'],
            'errors' => $metrics['errors'],
            'invalidations' => $metrics['invalidations'],
            'total_embeddings' => $metrics['total_embeddings'],
            'compression_enabled' => $this->compressionEnabled,
            'default_ttl' => $this->defaultTtl,
            'tenant_prefix' => $this->prefix,
        ];

        if ($this->useRedis) {
            try {
                // Informações do Redis
                $info = Redis::info('memory');
                $stats['redis_memory_used'] = $info['used_memory'] ?? 0;
                $stats['redis_memory_human'] = $info['used_memory_human'] ?? '0B';

                // Contagem de chaves do tenant
                $pattern = $this->prefix . '*';
                $stats['key_count'] = count(Redis::keys($pattern));
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
                $stats['key_count'] = 0;
                $stats['redis_memory_used'] = 0;
                $stats['redis_memory_human'] = '0B';
            }
        } else {
            $stats['key_count'] = 'N/A (fallback store)';
            $stats['redis_memory_used'] = 0;
            $stats['redis_memory_human'] = 'N/A (using ' . $this->fallbackStore . ')';
        }

        return $stats;
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
        if (!$this->useRedis) {
            // Para fallback stores, cleanup automático é feito pelo Laravel
            Log::debug('Cleanup skipped for fallback store', [
                'store' => $this->fallbackStore
            ]);
            return 0;
        }

        try {
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
        } catch (\Exception $e) {
            Log::error('Embedding cache cleanup failed', [
                'error' => $e->getMessage(),
                'tenant_prefix' => $this->prefix
            ]);
            return 0;
        }
    }

    /**
     * Análise de uso detalhada
     */
    public function getUsageAnalysis(): array
    {
        if (!$this->useRedis) {
            return [
                'backend' => 'fallback',
                'store' => $this->fallbackStore,
                'analysis_available' => false,
                'message' => 'Detailed analysis only available with Redis backend'
            ];
        }

        try {
            $pattern = $this->prefix . '*';
            $keys = Redis::keys($pattern);
            $analysis = [
                'backend' => 'redis',
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
        } catch (\Exception $e) {
            return [
                'backend' => 'redis',
                'error' => $e->getMessage(),
                'analysis_available' => false
            ];
        }
    }

    // Métodos privados

    /**
     * Verificar se Redis está disponível
     */
    private function isRedisAvailable(): bool
    {
        try {
            // Verificar se extensão Redis existe
            if (!extension_loaded('redis')) {
                return false;
            }

            // Verificar se classe Redis existe (facade Laravel)
            if (!class_exists('\Illuminate\Support\Facades\Redis')) {
                return false;
            }

            // Tentar conectar e fazer ping
            Redis::connection()->ping();
            return true;

        } catch (\Exception $e) {
            Log::debug('Redis not available for EmbeddingCache', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

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

        if ($this->useRedis) {
            try {
                $cached = Redis::get($key);
                if ($cached) {
                    return array_merge($this->metrics, json_decode($cached, true) ?: []);
                }
            } catch (\Exception $e) {
                Log::debug('Failed to get metrics from Redis, using local metrics', [
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            try {
                $cached = Cache::store($this->fallbackStore)->get($key);
                if ($cached) {
                    return array_merge($this->metrics, is_array($cached) ? $cached : (json_decode($cached, true) ?: []));
                }
            } catch (\Exception $e) {
                Log::debug('Failed to get metrics from fallback store, using local metrics', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->metrics;
    }

    private function incrementMetric(string $metric): void
    {
        $key = self::METRICS_KEY . ':' . $this->prefix;
        $metrics = $this->getMetrics();
        $metrics[$metric] = ($metrics[$metric] ?? 0) + 1;

        $metricsData = json_encode($metrics);
        $ttl = 86400 * 7; // 7 dias TTL para métricas

        if ($this->useRedis) {
            try {
                Redis::setex($key, $ttl, $metricsData);
            } catch (\Exception $e) {
                Log::debug('Failed to store metrics in Redis', [
                    'error' => $e->getMessage()
                ]);
                // Manter métricas apenas em memória se falhar
                $this->metrics[$metric] = ($this->metrics[$metric] ?? 0) + 1;
            }
        } else {
            try {
                Cache::store($this->fallbackStore)->put($key, $metricsData, $ttl);
            } catch (\Exception $e) {
                Log::debug('Failed to store metrics in fallback store', [
                    'error' => $e->getMessage()
                ]);
                // Manter métricas apenas em memória se falhar
                $this->metrics[$metric] = ($this->metrics[$metric] ?? 0) + 1;
            }
        }
    }

    private function scheduleCleanup(): void
    {
        // Só agendar cleanup para Redis, fallback stores fazem cleanup automático
        if (!$this->useRedis) {
            return;
        }

        try {
            $lastCleanup = Redis::get('last_cleanup:' . $this->prefix);
            $now = time();

            if (!$lastCleanup || ($now - (int)$lastCleanup) > self::CLEANUP_INTERVAL) {
                // Agendar limpeza assíncrona
                dispatch(function() {
                    $this->cleanup();
                    if ($this->useRedis) {
                        Redis::set('last_cleanup:' . $this->prefix, time());
                    }
                });
            }
        } catch (\Exception $e) {
            Log::debug('Failed to schedule cleanup', [
                'error' => $e->getMessage()
            ]);
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