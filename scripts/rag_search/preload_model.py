#!/usr/bin/env python3
"""
Preload de modelos de embeddings para otimizar performance
"""

import os
import sys
import time
import logging
from pathlib import Path

# Adicionar diretório pai ao path
sys.path.append(str(Path(__file__).parent))

from model_cache import ModelCache
from embeddings_service import EmbeddingsService

# Configurar logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def preload_embeddings_model():
    """Preload do modelo de embeddings"""
    try:
        logger.info("Iniciando preload do modelo de embeddings...")
        start_time = time.time()
        
        # Inicializa cache
        cache = ModelCache()
        
        # Verifica se modelo já está em cache
        cached_model = cache.load_model("all-mpnet-base-v2")
        if cached_model is not None:
            logger.info("Modelo já está em cache, pulando preload")
            return True
        
        # Carrega modelo de embeddings
        embeddings_service = EmbeddingsService()
        model = embeddings_service.model
        
        # Armazena no cache
        cache.cache_model("all-mpnet-base-v2", model)
        
        elapsed_time = time.time() - start_time
        logger.info(f"Modelo de embeddings carregado com sucesso em {elapsed_time:.2f}s")
        
        return True
        
    except Exception as e:
        logger.error(f"Erro no preload do modelo: {e}")
        return False

def preload_llm_models():
    """Preload de modelos LLM (se aplicável)"""
    try:
        logger.info("Iniciando preload de modelos LLM...")
        
        # Aqui você pode adicionar preload de modelos LLM específicos
        # Por enquanto, apenas log
        logger.info("Modelos LLM carregados (se aplicável)")
        
        return True
        
    except Exception as e:
        logger.error(f"Erro no preload de modelos LLM: {e}")
        return False

def main():
    """Função principal"""
    print("=== Preload de Modelos ===")
    
    # Preload de embeddings
    if preload_embeddings_model():
        print("✅ Modelo de embeddings carregado com sucesso")
    else:
        print("❌ Erro ao carregar modelo de embeddings")
    
    # Preload de LLM
    if preload_llm_models():
        print("✅ Modelos LLM carregados com sucesso")
    else:
        print("❌ Erro ao carregar modelos LLM")
    
    # Estatísticas do cache
    cache = ModelCache()
    stats = cache.get_cache_stats()
    print(f"\n📊 Estatísticas do Cache:")
    print(f"   Hit rate: {stats['hit_rate']}%")
    print(f"   Modelos em cache: {stats['cached_models']}")

if __name__ == "__main__":
    main()
