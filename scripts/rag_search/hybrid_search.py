#!/usr/bin/env python3
"""
Script para executar busca híbrida inteligente com grounding do Gemini.
"""

import sys
import json
import argparse
import os
from pathlib import Path
from typing import Dict, Any, Optional

# Fix para imports - adiciona diretório do script ao path
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

try:
    from hybrid_search_service import HybridSearchService
    from config import Config
except ImportError as e:
    error_response = {
        "success": False,
        "error": f"Erro ao importar módulos: {str(e)}. Verifique se todos os arquivos estão em {SCRIPT_DIR}",
        "query": "",
        "answer": "",
        "metadata": {
            "script_dir": str(SCRIPT_DIR),
            "python_path": sys.path[:3]
        }
    }
    print(json.dumps(error_response, ensure_ascii=False, indent=2))
    sys.exit(1)

def hybrid_search(query: str, document_id: Optional[int] = None, 
                  top_k: int = 5, threshold: float = 0.3, 
                  force_grounding: bool = False, db_config: Optional[Dict] = None,
                  llm_provider: str = 'gemini') -> Dict[str, Any]:
    """
    Busca híbrida inteligente com grounding do Gemini
    """
    try:
        # Inicializar sistema RAG
        if db_config:
            config = Config(db_config)
        else:
            config = Config()
        
        # Criar serviço de busca híbrida
        hybrid_service = HybridSearchService(config, llm_provider)
        
        # Executar busca
        result = hybrid_service.search(
            query=query,
            document_id=document_id,
            top_k=top_k,
            threshold=threshold,
            force_grounding=force_grounding
        )
        
        return result
        
    except Exception as e:
        return {
            "success": False,
            "error": f"Erro na busca híbrida: {str(e)}",
            "query": query,
            "answer": "",
            "sources": {'documents': [], 'web': []},
            "chunks_found": 0,
            "used_grounding": False,
            "search_method": "hybrid_error",
            "execution_time": 0,
            "metadata": {"hybrid_search": True, "error": True}
        }

def parse_args():
    parser = argparse.ArgumentParser(description="Busca Híbrida Inteligente com Grounding do Gemini")
    parser.add_argument('--query', type=str, required=True, help='A pergunta do usuário.')
    parser.add_argument('--document-id', type=int, default=None, help='ID do documento para busca (opcional).')
    parser.add_argument('--top-k', type=int, default=5, help='Número de chunks a buscar.')
    parser.add_argument('--threshold', type=float, default=0.3, help='Limiar de similaridade.')
    parser.add_argument('--force-grounding', action='store_true', help='Forçar o uso de grounding, ignorando a classificação da query.')
    parser.add_argument(
        '--db-config',
        type=str,
        default=None,
        help='JSON com configuração do banco (opcional)'
    )
    parser.add_argument(
        '--llm-provider',
        type=str,
        default='gemini',
        choices=['gemini', 'openai'],
        help='Provedor de LLM a usar (gemini, openai)'
    )
    parser.add_argument(
        '--verbose',
        action='store_true',
        help='Modo verbose (mostra logs de debug)'
    )
    
    return parser.parse_args()

def main():
    args = parse_args()
    
    try:
        db_config = None
        if args.db_config:
            db_config = json.loads(args.db_config)
        
        # Executar busca híbrida
        result = hybrid_search(
            query=args.query,
            document_id=args.document_id,
            top_k=args.top_k,
            threshold=args.threshold,
            force_grounding=args.force_grounding,
            db_config=db_config,
            llm_provider=args.llm_provider
        )
        
        # Output JSON para stdout (Laravel vai capturar isso)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        
        # Exit code baseado em sucesso
        sys.exit(0 if result.get("success", False) else 1)
        
    except Exception as e:
        error_result = {
            "success": False,
            "error": f"Erro fatal no script de busca híbrida: {str(e)}",
            "query": args.query,
            "answer": "",
            "sources": {'documents': [], 'web': []},
            "chunks_found": 0,
            "used_grounding": False,
            "search_method": "hybrid_error",
            "execution_time": 0,
            "metadata": {"hybrid_search": True, "error": True}
        }
        print(json.dumps(error_result, ensure_ascii=False, indent=2))
        sys.exit(1)

if __name__ == "__main__":
    main()
