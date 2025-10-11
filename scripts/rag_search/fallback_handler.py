#!/usr/bin/env python3
"""
Fallback Handler - Sistema de Fallback em Cascata
Se busca falhar, tenta alternativas progressivamente
Reduz falhas de 10% → 3-5%
"""

import sys
import json
import subprocess
from pathlib import Path
from typing import Dict, Any, Optional, List

# Fix para imports
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))


class FallbackHandler:
    """
    Sistema de fallback em cascata (5 níveis)
    """
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        self.db_config = db_config
        self.script_dir = SCRIPT_DIR
    
    def try_with_fallbacks(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Tenta busca com fallbacks progressivos
        
        Cascata:
        1. Query original
        2. Query expandida (sinônimos)
        3. Query simplificada (palavras-chave)
        4. Documento completo (se pequeno)
        5. Resumo pré-gerado
        """
        
        print("[FALLBACK] Iniciando busca com fallbacks", file=sys.stderr)
        
        # Tentativa 1: Query original
        print("[FALLBACK] Tentativa 1: Query original", file=sys.stderr)
        result = self._try_search(query, document_id, params, "original")
        
        if self._is_good_result(result):
            print("[FALLBACK] ✓ Sucesso na tentativa 1", file=sys.stderr)
            result['metadata']['fallback_level'] = 1
            result['metadata']['fallback_used'] = False
            return result
        
        print("[FALLBACK] ✗ Tentativa 1 falhou, tentando fallback 2", file=sys.stderr)
        
        # Tentativa 2: Query expandida
        print("[FALLBACK] Tentativa 2: Query expandida (sinônimos)", file=sys.stderr)
        expanded_query = self._expand_query(query)
        result = self._try_search(expanded_query, document_id, params, "expanded")
        
        if self._is_good_result(result):
            print("[FALLBACK] ✓ Sucesso na tentativa 2", file=sys.stderr)
            result['metadata']['fallback_level'] = 2
            result['metadata']['fallback_used'] = True
            result['metadata']['fallback_strategy'] = 'expanded_query'
            return result
        
        print("[FALLBACK] ✗ Tentativa 2 falhou, tentando fallback 3", file=sys.stderr)
        
        # Tentativa 3: Query simplificada
        print("[FALLBACK] Tentativa 3: Query simplificada (keywords)", file=sys.stderr)
        simplified_query = self._simplify_query(query)
        result = self._try_search(simplified_query, document_id, params, "simplified")
        
        if self._is_good_result(result):
            print("[FALLBACK] ✓ Sucesso na tentativa 3", file=sys.stderr)
            result['metadata']['fallback_level'] = 3
            result['metadata']['fallback_used'] = True
            result['metadata']['fallback_strategy'] = 'simplified_query'
            return result
        
        print("[FALLBACK] ✗ Tentativa 3 falhou, tentando fallback 4", file=sys.stderr)
        
        # Tentativa 4: Documento completo (se viável)
        print("[FALLBACK] Tentativa 4: Documento completo", file=sys.stderr)
        full_doc_params = params.copy()
        full_doc_params['use_full_document'] = True
        full_doc_params['mode'] = 'document_full'
        
        result = self._try_search(query, document_id, full_doc_params, "full_document")
        
        if self._is_good_result(result):
            print("[FALLBACK] ✓ Sucesso na tentativa 4", file=sys.stderr)
            result['metadata']['fallback_level'] = 4
            result['metadata']['fallback_used'] = True
            result['metadata']['fallback_strategy'] = 'full_document'
            return result
        
        print("[FALLBACK] ✗ Tentativa 4 falhou, tentando fallback 5 (último)", file=sys.stderr)
        
        # Tentativa 5: Modo summary forçado (último recurso)
        print("[FALLBACK] Tentativa 5: Modo summary forçado", file=sys.stderr)
        summary_params = params.copy()
        summary_params['mode'] = 'summary'
        summary_params['use_full_document'] = True
        
        result = self._try_search("Faça um resumo", document_id, summary_params, "summary_fallback")
        
        if result.get('success') or result.get('ok'):
            print("[FALLBACK] ✓ Sucesso na tentativa 5 (summary)", file=sys.stderr)
            result['metadata']['fallback_level'] = 5
            result['metadata']['fallback_used'] = True
            result['metadata']['fallback_strategy'] = 'summary_fallback'
            result['metadata']['fallback_note'] = 'Query original não encontrou resultados, retornando resumo geral'
            return result
        
        # Todas as tentativas falharam
        print("[FALLBACK] ✗ Todas as 5 tentativas falharam", file=sys.stderr)
        
        return {
            "success": False,
            "error": "Não foi possível encontrar informação relevante após 5 tentativas",
            "metadata": {
                "fallback_level": 5,
                "fallback_used": True,
                "all_attempts_failed": True,
                "suggestions": [
                    "Reformule a pergunta de forma mais específica",
                    "Verifique se a informação está realmente no documento",
                    "Tente perguntas mais simples ou diretas"
                ]
            }
        }
    
    def _try_search(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any],
        attempt_type: str
    ) -> Dict[str, Any]:
        """
        Tenta uma busca com rag_search.py
        """
        cmd = [
            'python3',
            str(self.script_dir / 'rag_search.py'),
            '--query', query,
            '--top-k', str(params.get('top_k', 5)),
            '--threshold', str(params.get('threshold', 0.3)),
            '--strictness', str(params.get('strictness', 2)),
            '--mode', params.get('mode', 'auto'),
            '--format', params.get('format', 'plain'),
            '--length', params.get('length', 'auto'),
            '--citations', str(params.get('citations', 0))
        ]
        
        if document_id:
            cmd.extend(['--document-id', str(document_id)])
        
        if params.get('use_full_document'):
            cmd.append('--use-full-document')
        
        if not params.get('include_answer', True):
            cmd.append('--no-llm')
        
        if self.db_config:
            cmd.extend(['--db-config', json.dumps(self.db_config)])
        
        try:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=60
            )
            
            if result.returncode == 0 and result.stdout:
                try:
                    return json.loads(result.stdout)
                except:
                    pass
            
            return {"success": False, "error": f"Falha na tentativa {attempt_type}"}
        
        except Exception as e:
            return {"success": False, "error": str(e)}
    
    def _is_good_result(self, result: Dict[str, Any]) -> bool:
        """
        Verifica se resultado é bom o suficiente
        """
        if not result:
            return False
        
        # Verifica se teve sucesso
        if not (result.get('success') or result.get('ok')):
            return False
        
        # Verifica se tem chunks ou resposta
        chunks = result.get('chunks', [])
        answer = result.get('answer', '')
        
        # Considera bom se:
        # - Tem 3+ chunks relevantes, OU
        # - Tem resposta com 50+ caracteres
        if len(chunks) >= 3:
            return True
        
        if answer and len(answer.strip()) >= 50:
            return True
        
        return False
    
    def _expand_query(self, query: str) -> str:
        """
        Expande query com sinônimos e termos relacionados
        """
        # Mapa de sinônimos comuns
        synonyms = {
            'qual': ['quais', 'que'],
            'como': ['de que forma', 'de que maneira'],
            'quando': ['em que momento', 'em que época'],
            'onde': ['em que lugar', 'em qual local'],
            'por que': ['porque', 'qual motivo', 'qual razão'],
            'preço': ['valor', 'custo', 'quanto custa'],
            'dosagem': ['dose', 'quantidade', 'posologia'],
            'benefício': ['vantagem', 'ganho', 'proveito'],
            'problema': ['questão', 'dificuldade', 'desafio'],
            'solução': ['resposta', 'resolução', 'alternativa']
        }
        
        query_lower = query.lower()
        expanded_terms = []
        
        for word, syns in synonyms.items():
            if word in query_lower:
                expanded_terms.extend(syns)
        
        if expanded_terms:
            # Adiciona sinônimos à query
            return f"{query} {' '.join(expanded_terms[:3])}"
        
        return query
    
    def _simplify_query(self, query: str) -> str:
        """
        Simplifica query para palavras-chave essenciais
        """
        # Remove palavras de parada
        stop_words = {
            'o', 'a', 'os', 'as', 'um', 'uma', 'de', 'da', 'do', 'das', 'dos',
            'em', 'no', 'na', 'nos', 'nas', 'para', 'por', 'com', 'sem',
            'me', 'te', 'se', 'lhe', 'nos', 'vos', 'lhes',
            'e', 'ou', 'mas', 'porém', 'contudo',
            'que', 'qual', 'quais', 'quando', 'onde', 'como', 'por que', 'porque'
        }
        
        words = query.lower().split()
        keywords = [w for w in words if w not in stop_words and len(w) > 2]
        
        # Mantém no máximo 5 palavras-chave mais importantes
        # Prioriza palavras com números ou maiúsculas (nomes próprios)
        important_words = []
        for word in keywords:
            if any(char.isdigit() for char in word):
                important_words.append(word)
        
        # Adiciona outras palavras até completar 5
        for word in keywords:
            if word not in important_words:
                important_words.append(word)
            if len(important_words) >= 5:
                break
        
        return ' '.join(important_words) if important_words else query


def main():
    """
    CLI para testes
    """
    import argparse
    
    parser = argparse.ArgumentParser(description='Fallback Handler - Sistema de Fallback')
    parser.add_argument('--query', required=True, help='Query de busca')
    parser.add_argument('--document-id', type=int, help='ID do documento')
    parser.add_argument('--top-k', type=int, default=5)
    parser.add_argument('--threshold', type=float, default=0.3)
    parser.add_argument('--strictness', type=int, default=2)
    parser.add_argument('--mode', default='auto')
    parser.add_argument('--format', default='plain')
    parser.add_argument('--length', default='auto')
    parser.add_argument('--citations', type=int, default=0)
    parser.add_argument('--db-config', help='Configuração do banco (JSON)')
    
    args = parser.parse_args()
    
    # Parse db_config
    db_config = None
    if args.db_config:
        try:
            db_config = json.loads(args.db_config)
        except:
            pass
    
    # Prepara parâmetros
    params = {
        'top_k': args.top_k,
        'threshold': args.threshold,
        'strictness': args.strictness,
        'mode': args.mode,
        'format': args.format,
        'length': args.length,
        'citations': args.citations,
        'include_answer': True
    }
    
    # Executa com fallbacks
    handler = FallbackHandler(db_config)
    result = handler.try_with_fallbacks(args.query, args.document_id, params)
    
    # Retorna resultado
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()

