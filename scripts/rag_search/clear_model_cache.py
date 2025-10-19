#!/usr/bin/env python3
"""
Script para limpar cache de modelos de embeddings
"""

import sys
from pathlib import Path

# Adicionar diretório pai ao path
sys.path.append(str(Path(__file__).parent))

from model_cache import ModelCache

def main():
    """Função principal"""
    print("=== Limpeza de Cache de Modelos ===")
    
    cache = ModelCache()
    
    # Mostra estatísticas antes da limpeza
    stats = cache.get_cache_stats()
    print(f"Modelos em cache: {stats['cached_models']}")
    print(f"Hit rate: {stats['hit_rate']}%")
    
    # Confirma limpeza
    response = input("\nDeseja realmente limpar o cache? (s/N): ")
    if response.lower() in ['s', 'sim', 'y', 'yes']:
        if cache.clear_cache():
            print("✅ Cache limpo com sucesso!")
        else:
            print("❌ Erro ao limpar cache")
    else:
        print("Operação cancelada")

if __name__ == "__main__":
    main()
