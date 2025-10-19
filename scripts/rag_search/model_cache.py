#!/usr/bin/env python3
"""
Cache de modelos de embeddings para otimizar performance
"""

import os
import json
import pickle
import hashlib
from pathlib import Path
from typing import Dict, Any, Optional
import logging

# Configurar logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class ModelCache:
    """Cache para modelos de embeddings"""
    
    def __init__(self, cache_dir: str = "/tmp/rag_model_cache"):
        self.cache_dir = Path(cache_dir)
        self.cache_dir.mkdir(exist_ok=True)
        self.cache_file = self.cache_dir / "model_cache.pkl"
        self.metadata_file = self.cache_dir / "cache_metadata.json"
        
        # Carrega metadados do cache
        self.metadata = self._load_metadata()
    
    def _load_metadata(self) -> Dict[str, Any]:
        """Carrega metadados do cache"""
        if self.metadata_file.exists():
            try:
                with open(self.metadata_file, 'r') as f:
                    return json.load(f)
            except Exception as e:
                logger.warning(f"Erro ao carregar metadados do cache: {e}")
        return {
            'models': {},
            'stats': {
                'hits': 0,
                'misses': 0,
                'created_at': None
            }
        }
    
    def _save_metadata(self):
        """Salva metadados do cache"""
        try:
            with open(self.metadata_file, 'w') as f:
                json.dump(self.metadata, f, indent=2)
        except Exception as e:
            logger.warning(f"Erro ao salvar metadados do cache: {e}")
    
    def _get_model_key(self, model_name: str, model_version: str = None) -> str:
        """Gera chave única para o modelo"""
        key_data = f"{model_name}_{model_version or 'default'}"
        return hashlib.md5(key_data.encode()).hexdigest()
    
    def cache_model(self, model_name: str, model_object: Any, model_version: str = None) -> bool:
        """Armazena modelo no cache"""
        try:
            model_key = self._get_model_key(model_name, model_version)
            
            # Salva modelo
            model_file = self.cache_dir / f"model_{model_key}.pkl"
            with open(model_file, 'wb') as f:
                pickle.dump(model_object, f)
            
            # Atualiza metadados
            self.metadata['models'][model_key] = {
                'name': model_name,
                'version': model_version,
                'cached_at': str(Path().cwd()),
                'file_size': model_file.stat().st_size
            }
            
            self._save_metadata()
            logger.info(f"Modelo {model_name} armazenado no cache")
            return True
            
        except Exception as e:
            logger.error(f"Erro ao armazenar modelo no cache: {e}")
            return False
    
    def load_model(self, model_name: str, model_version: str = None) -> Optional[Any]:
        """Carrega modelo do cache"""
        try:
            model_key = self._get_model_key(model_name, model_version)
            model_file = self.cache_dir / f"model_{model_key}.pkl"
            
            if not model_file.exists():
                self.metadata['stats']['misses'] += 1
                self._save_metadata()
                return None
            
            with open(model_file, 'rb') as f:
                model = pickle.load(f)
            
            self.metadata['stats']['hits'] += 1
            self._save_metadata()
            logger.info(f"Modelo {model_name} carregado do cache")
            return model
            
        except Exception as e:
            logger.error(f"Erro ao carregar modelo do cache: {e}")
            self.metadata['stats']['misses'] += 1
            self._save_metadata()
            return None
    
    def clear_cache(self) -> bool:
        """Limpa cache de modelos"""
        try:
            # Remove arquivos de modelo
            for model_file in self.cache_dir.glob("model_*.pkl"):
                model_file.unlink()
            
            # Remove arquivos de metadados
            if self.metadata_file.exists():
                self.metadata_file.unlink()
            
            # Reinicia metadados
            self.metadata = {
                'models': {},
                'stats': {
                    'hits': 0,
                    'misses': 0,
                    'created_at': None
                }
            }
            
            logger.info("Cache de modelos limpo com sucesso")
            return True
            
        except Exception as e:
            logger.error(f"Erro ao limpar cache: {e}")
            return False
    
    def get_cache_stats(self) -> Dict[str, Any]:
        """Retorna estatísticas do cache"""
        total_requests = self.metadata['stats']['hits'] + self.metadata['stats']['misses']
        hit_rate = (self.metadata['stats']['hits'] / total_requests * 100) if total_requests > 0 else 0
        
        return {
            'hits': self.metadata['stats']['hits'],
            'misses': self.metadata['stats']['misses'],
            'hit_rate': round(hit_rate, 2),
            'cached_models': len(self.metadata['models']),
            'cache_dir': str(self.cache_dir)
        }

def main():
    """Função principal para teste"""
    cache = ModelCache()
    
    print("=== Cache de Modelos de Embeddings ===")
    print(f"Diretório: {cache.cache_dir}")
    
    stats = cache.get_cache_stats()
    print(f"Modelos em cache: {stats['cached_models']}")
    print(f"Hit rate: {stats['hit_rate']}%")
    print(f"Hits: {stats['hits']}, Misses: {stats['misses']}")

if __name__ == "__main__":
    main()
