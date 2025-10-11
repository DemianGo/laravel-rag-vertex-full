#!/usr/bin/env python3
"""
Cache Layer - Sistema de Cache em 3 Níveis
L1: Queries idênticas (hit rate: 30%)
L2: Queries similares (hit rate: 20%)
L3: Chunks frequentes (hit rate: 40%)
Meta: Reduzir latência de 6s → 2s
"""

import sys
import json
import hashlib
import time
from pathlib import Path
from typing import Dict, Any, Optional

# Fix para imports
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

try:
    import redis
    REDIS_AVAILABLE = True
except ImportError:
    REDIS_AVAILABLE = False
    print("[CACHE] Redis não disponível, usando cache em arquivo", file=sys.stderr)


class CacheLayer:
    """
    Sistema de cache em 3 níveis
    """
    
    def __init__(self, redis_host: str = 'localhost', redis_port: int = 6379):
        self.redis_client = None
        self.cache_dir = SCRIPT_DIR / '.cache'
        self.cache_dir.mkdir(exist_ok=True)
        
        # Tenta conectar ao Redis
        if REDIS_AVAILABLE:
            try:
                self.redis_client = redis.Redis(
                    host=redis_host,
                    port=redis_port,
                    db=0,
                    decode_responses=True,
                    socket_timeout=2
                )
                # Testa conexão
                self.redis_client.ping()
                print("[CACHE] Redis conectado com sucesso", file=sys.stderr)
            except Exception as e:
                print(f"[CACHE] Redis falhou, usando cache em arquivo: {e}", file=sys.stderr)
                self.redis_client = None
    
    def get_cached(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any]
    ) -> Optional[Dict[str, Any]]:
        """
        Busca em cache (L1 → L2 → L3)
        
        Returns:
            Resultado cacheado ou None
        """
        
        # L1: Cache de query exata
        cache_key = self._generate_cache_key(query, document_id, params)
        
        print(f"[CACHE] Buscando em L1 (query exata): {cache_key[:30]}...", file=sys.stderr)
        result = self._get_from_cache(cache_key, 'L1')
        
        if result:
            print("[CACHE] ✓ HIT em L1 (query exata)", file=sys.stderr)
            result['metadata']['cache_hit'] = True
            result['metadata']['cache_level'] = 'L1'
            return result
        
        # L2: Cache de queries similares (simplificado por enquanto)
        # TODO: Implementar busca por similaridade de embeddings
        print("[CACHE] ✗ MISS em L1, pulando L2 (não implementado)", file=sys.stderr)
        
        # L3: Cache de chunks frequentes (simplificado por enquanto)
        # TODO: Implementar pre-warming de chunks populares
        print("[CACHE] ✗ MISS em L2, pulando L3 (não implementado)", file=sys.stderr)
        
        return None
    
    def set_cached(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any],
        result: Dict[str, Any],
        ttl: int = 3600  # 1 hora
    ) -> bool:
        """
        Salva resultado em cache
        """
        cache_key = self._generate_cache_key(query, document_id, params)
        
        print(f"[CACHE] Salvando em L1: {cache_key[:30]}...", file=sys.stderr)
        
        # Adiciona metadados de cache
        cached_result = result.copy()
        if 'metadata' not in cached_result:
            cached_result['metadata'] = {}
        cached_result['metadata']['cached_at'] = time.time()
        cached_result['metadata']['cache_ttl'] = ttl
        
        return self._save_to_cache(cache_key, cached_result, ttl, 'L1')
    
    def _generate_cache_key(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any]
    ) -> str:
        """
        Gera chave de cache única
        """
        # Normaliza query (lowercase, remove espaços extras)
        query_normalized = ' '.join(query.lower().split())
        
        # Cria string com todos os parâmetros relevantes
        cache_string = f"{query_normalized}|doc:{document_id}|"
        cache_string += f"mode:{params.get('mode', 'auto')}|"
        cache_string += f"format:{params.get('format', 'plain')}|"
        cache_string += f"strictness:{params.get('strictness', 2)}|"
        cache_string += f"topk:{params.get('top_k', 5)}"
        
        # Hash MD5 para chave curta
        return hashlib.md5(cache_string.encode()).hexdigest()
    
    def _get_from_cache(self, key: str, level: str) -> Optional[Dict[str, Any]]:
        """
        Busca em cache (Redis ou arquivo)
        """
        # Tenta Redis primeiro
        if self.redis_client:
            try:
                cached = self.redis_client.get(f"rag:{level}:{key}")
                if cached:
                    print(f"[CACHE] ✓ Encontrado no Redis ({level})", file=sys.stderr)
                    return json.loads(cached)
            except Exception as e:
                print(f"[CACHE] Erro ao buscar no Redis: {e}", file=sys.stderr)
        
        # Fallback: arquivo
        cache_file = self.cache_dir / f"{level}_{key}.json"
        if cache_file.exists():
            try:
                # Verifica se não expirou
                age = time.time() - cache_file.stat().st_mtime
                if age < 3600:  # 1 hora
                    with open(cache_file, 'r', encoding='utf-8') as f:
                        return json.load(f)
                else:
                    # Expirado, remove
                    cache_file.unlink()
            except Exception as e:
                print(f"[CACHE] Erro ao ler arquivo: {e}", file=sys.stderr)
        
        return None
    
    def _save_to_cache(
        self,
        key: str,
        data: Dict[str, Any],
        ttl: int,
        level: str
    ) -> bool:
        """
        Salva em cache (Redis ou arquivo)
        """
        # Tenta Redis primeiro
        if self.redis_client:
            try:
                self.redis_client.setex(
                    f"rag:{level}:{key}",
                    ttl,
                    json.dumps(data, ensure_ascii=False)
                )
                print(f"[CACHE] ✓ Salvo no Redis ({level})", file=sys.stderr)
                return True
            except Exception as e:
                print(f"[CACHE] Erro ao salvar no Redis: {e}", file=sys.stderr)
        
        # Fallback: arquivo
        try:
            cache_file = self.cache_dir / f"{level}_{key}.json"
            with open(cache_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            print(f"[CACHE] ✓ Salvo em arquivo ({level})", file=sys.stderr)
            return True
        except Exception as e:
            print(f"[CACHE] Erro ao salvar em arquivo: {e}", file=sys.stderr)
            return False
    
    def clear_cache(self, pattern: str = '*') -> int:
        """
        Limpa cache
        
        Args:
            pattern: Padrão para limpar (ex: 'doc:142:*')
        
        Returns:
            Número de chaves removidas
        """
        count = 0
        
        # Limpa Redis
        if self.redis_client:
            try:
                keys = self.redis_client.keys(f"rag:*:{pattern}")
                if keys:
                    count += self.redis_client.delete(*keys)
                print(f"[CACHE] Removidas {count} chaves do Redis", file=sys.stderr)
            except Exception as e:
                print(f"[CACHE] Erro ao limpar Redis: {e}", file=sys.stderr)
        
        # Limpa arquivos
        try:
            for cache_file in self.cache_dir.glob(f"*{pattern}*.json"):
                cache_file.unlink()
                count += 1
            print(f"[CACHE] Removidos {count} arquivos de cache", file=sys.stderr)
        except Exception as e:
            print(f"[CACHE] Erro ao limpar arquivos: {e}", file=sys.stderr)
        
        return count
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Retorna estatísticas do cache
        """
        stats = {
            "redis_available": self.redis_client is not None,
            "cache_dir": str(self.cache_dir),
            "file_cache_count": len(list(self.cache_dir.glob("*.json")))
        }
        
        if self.redis_client:
            try:
                info = self.redis_client.info('stats')
                stats['redis_stats'] = {
                    "total_keys": self.redis_client.dbsize(),
                    "hits": info.get('keyspace_hits', 0),
                    "misses": info.get('keyspace_misses', 0),
                    "hit_rate": self._calculate_hit_rate(
                        info.get('keyspace_hits', 0),
                        info.get('keyspace_misses', 0)
                    )
                }
            except Exception as e:
                stats['redis_error'] = str(e)
        
        return stats
    
    def _calculate_hit_rate(self, hits: int, misses: int) -> float:
        """Calcula taxa de acerto do cache"""
        total = hits + misses
        return round((hits / total * 100), 2) if total > 0 else 0.0


def main():
    """
    CLI para testes e gerenciamento
    """
    import argparse
    
    parser = argparse.ArgumentParser(description='Cache Layer - Sistema de Cache')
    parser.add_argument('--action', choices=['stats', 'clear'], default='stats',
                       help='Ação: stats (estatísticas) ou clear (limpar)')
    parser.add_argument('--pattern', default='*', help='Padrão para limpar (ex: doc:142:*)')
    
    args = parser.parse_args()
    
    cache = CacheLayer()
    
    if args.action == 'stats':
        stats = cache.get_stats()
        print(json.dumps(stats, ensure_ascii=False, indent=2))
    
    elif args.action == 'clear':
        count = cache.clear_cache(args.pattern)
        print(json.dumps({
            "success": True,
            "keys_removed": count,
            "pattern": args.pattern
        }, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()

