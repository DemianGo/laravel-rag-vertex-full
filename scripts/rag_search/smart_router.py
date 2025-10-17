#!/usr/bin/env python3
"""
Smart Router - Camada de Inteligência para Sistema RAG
Intercepta queries e decide automaticamente a melhor estratégia
NÃO MODIFICA o código existente - apenas adiciona inteligência por cima
"""

import sys
import json
import argparse
import subprocess
from pathlib import Path
from typing import Dict, Any, Optional

# Fix para imports
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

try:
    from database import DatabaseManager
    from config import Config
    from pre_validator import PreValidator
    from fallback_handler import FallbackHandler
    from cache_layer import CacheLayer
except ImportError as e:
    error_response = {
        "success": False,
        "error": f"Erro ao importar módulos: {str(e)}",
        "metadata": {"script_dir": str(SCRIPT_DIR)}
    }
    print(json.dumps(error_response, ensure_ascii=False, indent=2))
    sys.exit(1)


class SmartRouter:
    """
    Roteador Inteligente que decide a melhor estratégia de busca
    """
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        self.db_manager = DatabaseManager(db_config)
        self.config = Config(db_config) if db_config else Config()
        self.validator = PreValidator(db_config)
        self.fallback_handler = FallbackHandler(db_config)
        self.cache = CacheLayer()
    
    def route(
        self,
        query: str,
        document_id: Optional[int] = None,
        top_k: int = 5,
        threshold: float = 0.3,
        include_answer: bool = True,
        strictness: int = 2,
        mode: str = "auto",
        format: str = "plain",
        length: str = "auto",
        citations: int = 0,
        use_full_document: bool = False,
        db_config: Optional[Dict[str, str]] = None
    ) -> Dict[str, Any]:
        """
        Decide a melhor estratégia e chama rag_search.py com parâmetros otimizados
        
        Returns:
            Resultado da busca com metadados de decisão
        """
        
        print(f"[SMART_ROUTER] Analisando query: {query[:50]}...", file=sys.stderr)
        
        # 1. Validação preventiva (ANTES do cache para evitar cachear erros)
        print(f"[SMART_ROUTER] Validando query e documento...", file=sys.stderr)
        validation = self.validator.validate_all(query, document_id)
        
        if not validation["valid"]:
            print(f"[SMART_ROUTER] ✗ Validação falhou: {validation['errors']}", file=sys.stderr)
            return {
                "success": False,
                "error": "; ".join(validation["errors"]),
                "suggestions": validation["suggestions"],
                "metadata": {
                    "validation_failed": True,
                    "errors": validation["errors"],
                    "suggestions": validation["suggestions"]
                }
            }
        
        if validation.get("warnings"):
            print(f"[SMART_ROUTER] ⚠ Avisos: {validation['warnings']}", file=sys.stderr)
        
        # 2. Análise do contexto
        context = self._analyze_context(query, document_id)
        
        print(f"[SMART_ROUTER] Contexto detectado:", file=sys.stderr)
        print(f"  - Especificidade: {context['query_specificity']:.2f}", file=sys.stderr)
        print(f"  - Tipo: {context['query_type']}", file=sys.stderr)
        print(f"  - Tamanho doc: {context['doc_size']} páginas", file=sys.stderr)
        
        # 3. Decisão de estratégia
        strategy = self._decide_strategy(context, mode, use_full_document)
        
        print(f"[SMART_ROUTER] Estratégia escolhida: {strategy['name']}", file=sys.stderr)
        print(f"[SMART_ROUTER] Razão: {strategy['reason']}", file=sys.stderr)
        print(f"[SMART_ROUTER] Confiança: {strategy['confidence']:.2f}", file=sys.stderr)
        
        # 4. Otimização de parâmetros
        optimized_params = self._optimize_parameters(strategy, context, {
            'top_k': top_k,
            'threshold': threshold,
            'strictness': strictness,
            'mode': mode,
            'format': format,
            'length': length,
            'citations': citations,
            'use_full_document': use_full_document
        })
        
        print(f"[SMART_ROUTER] Parâmetros otimizados:", file=sys.stderr)
        for key, value in optimized_params.items():
            if value != locals().get(key):
                print(f"  - {key}: {locals().get(key)} → {value}", file=sys.stderr)
        
        # 4.5. Verifica cache COM parâmetros otimizados
        print(f"[SMART_ROUTER] Verificando cache (pós-otimização)...", file=sys.stderr)
        cached_result = self.cache.get_cached(query, document_id, optimized_params)
        
        if cached_result:
            print(f"[SMART_ROUTER] ✓ Cache HIT! Retornando resultado cacheado", file=sys.stderr)
            return cached_result
        
        print(f"[SMART_ROUTER] ✗ Cache MISS, executando busca...", file=sys.stderr)
        
        # 5. Chama rag_search.py com parâmetros otimizados (COM FALLBACK)
        result = self._call_with_fallback(query, document_id, optimized_params, db_config)
        
        # 5.5. Salva resultado em cache (se sucesso)
        if result.get('success') or result.get('ok'):
            self.cache.set_cached(query, document_id, optimized_params, result, ttl=3600)
        
        # 6. Adiciona metadados de decisão
        if 'metadata' not in result:
            result['metadata'] = {}
        
        result['metadata']['smart_router'] = {
            'strategy': strategy['name'],
            'reason': strategy['reason'],
            'confidence': strategy['confidence'],
            'context_analysis': context,
            'parameters_optimized': optimized_params != locals()
        }
        
        return result
    
    def _analyze_context(self, query: str, document_id: Optional[int]) -> Dict[str, Any]:
        """
        Analisa contexto da query e documento
        """
        context = {
            'query_specificity': self._calculate_specificity(query),
            'query_type': self._classify_query_type(query),
            'query_length': len(query.split()),
            'has_numbers': any(char.isdigit() for char in query),
            'doc_size': 0,
            'doc_type': 'unknown',
            'has_embeddings': False,
            'chunk_count': 0
        }
        
        # Análise do documento (se especificado)
        if document_id:
            try:
                # Busca informações do documento
                doc_query = """
                    SELECT 
                        d.title,
                        COUNT(c.id) as total_chunks,
                        COUNT(CASE WHEN c.embedding IS NOT NULL THEN 1 END) as chunks_with_embeddings
                    FROM documents d
                    LEFT JOIN chunks c ON c.document_id = d.id
                    WHERE d.id = %s
                    GROUP BY d.id, d.title
                """
                
                result = self.db_manager.execute_query(doc_query, [document_id])
                
                if result and len(result) > 0:
                    doc = result[0]
                    # Suporta dict ou tuple
                    if isinstance(doc, dict):
                        context['total_chunks'] = doc.get('total_chunks', 0)
                        context['chunk_count'] = doc.get('total_chunks', 0)
                        context['has_embeddings'] = doc.get('chunks_with_embeddings', 0) > 0
                    else:
                        context['total_chunks'] = doc[1]
                        context['chunk_count'] = doc[1]
                        context['has_embeddings'] = doc[2] > 0
                    
                    # Estima tamanho do documento (1 chunk ≈ 1-2 páginas)
                    context['doc_size'] = max(1, context['total_chunks'] // 2)
                    
                    # Tenta detectar tipo do documento pelo título
                    title_val = doc.get('title', '') if isinstance(doc, dict) else doc[0]
                    title = title_val.lower() if title_val else ''
                    if any(word in title for word in ['bula', 'medicamento', 'remédio']):
                        context['doc_type'] = 'medical'
                    elif any(word in title for word in ['contrato', 'acordo', 'termo']):
                        context['doc_type'] = 'legal'
                    elif any(word in title for word in ['artigo', 'paper', 'estudo']):
                        context['doc_type'] = 'academic'
                    elif any(word in title for word in ['catálogo', 'produto', 'preço']):
                        context['doc_type'] = 'catalog'
                    
            except Exception as e:
                print(f"[SMART_ROUTER] Erro ao analisar documento: {e}", file=sys.stderr)
        
        return context
    
    def _calculate_specificity(self, query: str) -> float:
        """
        Calcula especificidade da query (0.0 = genérica, 1.0 = específica)
        """
        score = 0.5  # baseline
        query_lower = query.lower()
        
        # Palavras genéricas diminuem score
        generic_words = ['resumo', 'fale sobre', 'me explique', 'o que é', 'descreva']
        for word in generic_words:
            if word in query_lower:
                score -= 0.15
        
        # Palavras específicas aumentam score
        specific_words = ['qual', 'quanto', 'quando', 'onde', 'como', 'quem']
        for word in specific_words:
            if word in query_lower:
                score += 0.1
        
        # Números aumentam especificidade
        if any(char.isdigit() for char in query):
            score += 0.15
        
        # Query longa e detalhada = mais específica
        word_count = len(query.split())
        if word_count > 10:
            score += 0.1
        elif word_count < 5:
            score -= 0.1
        
        # Nomes próprios (palavras capitalizadas) aumentam especificidade
        capitalized_words = sum(1 for word in query.split() if word and word[0].isupper())
        if capitalized_words > 1:
            score += 0.1
        
        return max(0.0, min(1.0, score))
    
    def _classify_query_type(self, query: str) -> str:
        """
        Classifica tipo da pergunta
        """
        query_lower = query.lower()
        
        # Definição
        if any(w in query_lower for w in ['o que é', 'defina', 'conceito de', 'significado']):
            return "definition"
        
        # Comparação
        if any(w in query_lower for w in ['diferença entre', 'compare', 'versus', 'vs', 'comparação']):
            return "comparison"
        
        # Lista
        if any(w in query_lower for w in ['liste', 'quais são', 'enumere', 'cite', 'listar']):
            return "list"
        
        # Resumo
        if any(w in query_lower for w in ['resumo', 'resuma', 'sintetize', 'sumário']):
            return "summary"
        
        # Citação
        if any(w in query_lower for w in ['cite', 'transcreva', 'texto exato', 'literalmente']):
            return "quote"
        
        # Explicação
        if any(w in query_lower for w in ['explique', 'como funciona', 'por que', 'porque']):
            return "explanation"
        
        # Específica (default)
        return "specific"
    
    def _decide_strategy(
        self, 
        context: Dict[str, Any], 
        mode: str,
        use_full_document: bool
    ) -> Dict[str, Any]:
        """
        Decide a melhor estratégia baseado no contexto
        """
        
        # Se usuário forçou documento completo, respeita
        if use_full_document or mode == 'document_full':
            return {
                'name': 'DOCUMENT_FULL',
                'reason': 'Usuário solicitou documento completo',
                'confidence': 1.0
            }
        
        # REGRA 1: Documento pequeno + query genérica = DOCUMENTO COMPLETO
        if context['doc_size'] < 30 and context['query_specificity'] < 0.3:
            return {
                'name': 'DOCUMENT_FULL',
                'reason': f"Documento pequeno ({context['doc_size']} pág) + query genérica (spec: {context['query_specificity']:.2f})",
                'confidence': 0.9
            }
        
        # REGRA 2: Query muito específica = RAG PADRÃO
        if context['query_specificity'] > 0.7:
            return {
                'name': 'RAG_STANDARD',
                'reason': f"Query muito específica (spec: {context['query_specificity']:.2f})",
                'confidence': 0.85
            }
        
        # REGRA 3: Resumo de documento pequeno = DOCUMENTO COMPLETO
        if context['query_type'] == 'summary' and context['doc_size'] < 50:
            return {
                'name': 'DOCUMENT_FULL',
                'reason': f"Resumo solicitado + documento pequeno ({context['doc_size']} pág)",
                'confidence': 0.8
            }
        
        # REGRA 4: Sem embeddings = FTS ou DOCUMENTO COMPLETO
        if not context['has_embeddings']:
            chunk_count = context.get('chunk_count', 0)
            
            # OTIMIZAÇÃO CRÍTICA: Documentos grandes (>100 chunks) NUNCA usam DOCUMENT_FULL
            # Mesmo sem página count, limite absoluto de chunks
            if chunk_count > 100:
                return {
                    'name': 'RAG_FTS_ONLY',
                    'reason': f"Sem embeddings + documento MUITO grande ({chunk_count} chunks) - FTS obrigatório",
                    'confidence': 0.9
                }
            
            # Documentos médios (50-100 chunks) também usam FTS
            if chunk_count > 50:
                return {
                    'name': 'RAG_FTS_ONLY',
                    'reason': f"Sem embeddings + documento grande ({chunk_count} chunks) - usa FTS",
                    'confidence': 0.8
                }
            
            # Apenas documentos REALMENTE pequenos (<50 chunks E <20 pág) usam DOCUMENT_FULL
            if context['doc_size'] < 20 and chunk_count < 50:
                return {
                    'name': 'DOCUMENT_FULL',
                    'reason': f"Sem embeddings + documento pequeno ({context['doc_size']} pág, {chunk_count} chunks)",
                    'confidence': 0.75
                }
            else:
                return {
                    'name': 'RAG_FTS_ONLY',
                    'reason': f"Sem embeddings + documento médio ({context['doc_size']} pág, {chunk_count} chunks) - usa FTS",
                    'confidence': 0.7
                }
        
        # REGRA 5: Documento grande = sempre RAG
        if context['doc_size'] > 100:
            return {
                'name': 'RAG_STANDARD',
                'reason': f"Documento muito grande ({context['doc_size']} pág)",
                'confidence': 0.95
            }
        
        # REGRA 6: Documento médio + query média = HÍBRIDO
        if 30 <= context['doc_size'] <= 100 and 0.3 <= context['query_specificity'] <= 0.7:
            return {
                'name': 'HYBRID',
                'reason': f"Doc médio ({context['doc_size']} pág) + query média (spec: {context['query_specificity']:.2f})",
                'confidence': 0.75
            }
        
        # Default: HÍBRIDO (mais seguro)
        return {
            'name': 'HYBRID',
            'reason': 'Estratégia padrão com fallback automático',
            'confidence': 0.6
        }
    
    def _optimize_parameters(
        self,
        strategy: Dict[str, Any],
        context: Dict[str, Any],
        original_params: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Otimiza parâmetros baseado na estratégia escolhida
        """
        params = original_params.copy()
        
        # Otimizações por estratégia
        if strategy['name'] == 'DOCUMENT_FULL':
            params['use_full_document'] = True
            params['mode'] = 'document_full' if params['mode'] == 'auto' else params['mode']
        
        elif strategy['name'] == 'RAG_STANDARD':
            # Ajusta top_k baseado na especificidade
            if context['query_specificity'] > 0.7:
                params['top_k'] = min(params['top_k'], 3)  # Query específica = menos chunks
            else:
                params['top_k'] = max(params['top_k'], 8)  # Query genérica = mais chunks
            
            # Ajusta threshold baseado na confiança
            if strategy['confidence'] > 0.8:
                params['threshold'] = 0.25  # Mais rigoroso
            else:
                params['threshold'] = 0.15  # Mais permissivo
        
        elif strategy['name'] == 'HYBRID':
            # Parâmetros balanceados
            params['top_k'] = 5
            params['threshold'] = 0.2
        
        elif strategy['name'] == 'RAG_FTS_ONLY':
            # Força uso de FTS
            params['threshold'] = 0.0  # Desabilita filtro de similaridade vetorial
            params['top_k'] = 8  # Mais chunks para compensar falta de embeddings
        
        # Otimizações por tipo de query
        if context['query_type'] == 'summary':
            params['mode'] = 'summary' if params['mode'] == 'auto' else params['mode']
            params['length'] = 'medium' if params['length'] == 'auto' else params['length']
        
        elif context['query_type'] == 'list':
            params['mode'] = 'list' if params['mode'] == 'auto' else params['mode']
        
        elif context['query_type'] == 'quote':
            params['mode'] = 'quote' if params['mode'] == 'auto' else params['mode']
            params['strictness'] = max(params['strictness'], 2)  # Mais rigoroso para citações
        
        # Otimizações por tipo de documento
        if context['doc_type'] in ['medical', 'legal']:
            params['strictness'] = max(params['strictness'], 2)  # Mais rigoroso
            params['citations'] = max(params['citations'], 1)  # Sempre com citação
        
        return params
    
    def _call_with_fallback(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any],
        db_config: Optional[Dict[str, str]]
    ) -> Dict[str, Any]:
        """
        Chama rag_search.py com fallback automático se falhar
        """
        # Primeira tentativa: chamada direta
        result = self._call_rag_search(query, document_id, params, db_config)
        
        # Se resultado é bom, retorna
        if self._is_good_result(result):
            return result
        
        # Se falhou, usa fallback handler
        print(f"[SMART_ROUTER] Resultado insuficiente, ativando fallback handler", file=sys.stderr)
        return self.fallback_handler.try_with_fallbacks(query, document_id, params)
    
    def _is_good_result(self, result: Dict[str, Any]) -> bool:
        """
        Verifica se resultado é bom o suficiente
        """
        if not result:
            return False
        
        if not (result.get('success') or result.get('ok')):
            return False
        
        chunks = result.get('chunks', [])
        answer = result.get('answer', '')
        
        # Considera bom se tem 3+ chunks OU resposta com 50+ caracteres
        return len(chunks) >= 3 or (answer and len(answer.strip()) >= 50)
    
    def _call_rag_search(
        self,
        query: str,
        document_id: Optional[int],
        params: Dict[str, Any],
        db_config: Optional[Dict[str, str]]
    ) -> Dict[str, Any]:
        """
        Chama rag_search.py com os parâmetros otimizados
        """
        
        # Monta comando
        cmd = [
            'python3',
            str(SCRIPT_DIR / 'rag_search.py'),
            '--query', query,
            '--top-k', str(params['top_k']),
            '--threshold', str(params['threshold']),
            '--strictness', str(params['strictness']),
            '--mode', params['mode'],
            '--format', params['format'],
            '--length', params['length'],
            '--citations', str(params['citations'])
        ]
        
        if document_id:
            cmd.extend(['--document-id', str(document_id)])
        
        if params['use_full_document']:
            cmd.append('--use-full-document')
        
        if not params.get('include_answer', True):
            cmd.append('--no-llm')
        
        if db_config:
            cmd.extend(['--db-config', json.dumps(db_config)])
        
        print(f"[SMART_ROUTER] Executando: {' '.join(cmd[:10])}...", file=sys.stderr)
        
        try:
            # Executa rag_search.py
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=120  # 2 minutos de timeout
            )
            
            if result.returncode != 0:
                return {
                    "success": False,
                    "error": f"rag_search.py falhou: {result.stderr}",
                    "metadata": {"exit_code": result.returncode}
                }
            
            # Parse JSON response
            try:
                return json.loads(result.stdout)
            except json.JSONDecodeError as e:
                return {
                    "success": False,
                    "error": f"Resposta inválida do rag_search.py: {str(e)}",
                    "raw_output": result.stdout[:500]
                }
        
        except subprocess.TimeoutExpired:
            return {
                "success": False,
                "error": "Timeout ao executar rag_search.py (> 2 minutos)"
            }
        
        except Exception as e:
            return {
                "success": False,
                "error": f"Erro ao executar rag_search.py: {str(e)}"
            }


def main():
    """
    CLI do Smart Router
    """
    parser = argparse.ArgumentParser(description='Smart Router - Camada de Inteligência RAG')
    
    # Parâmetros obrigatórios
    parser.add_argument('--query', required=True, help='Query de busca')
    
    # Parâmetros opcionais
    parser.add_argument('--document-id', type=int, help='ID do documento')
    parser.add_argument('--top-k', type=int, default=5, help='Número de chunks (1-30)')
    parser.add_argument('--threshold', type=float, default=0.3, help='Threshold de similaridade (0-1)')
    parser.add_argument('--no-llm', action='store_true', help='Desabilita geração de resposta com LLM')
    parser.add_argument('--strictness', type=int, default=2, choices=[0,1,2,3], help='Nível de rigor (0-3)')
    parser.add_argument('--mode', default='auto', help='Modo de resposta')
    parser.add_argument('--format', default='plain', help='Formato de saída')
    parser.add_argument('--length', default='auto', help='Comprimento da resposta')
    parser.add_argument('--citations', type=int, default=0, help='Número de citações')
    parser.add_argument('--use-full-document', action='store_true', help='Usa documento completo')
    parser.add_argument('--db-config', help='Configuração do banco (JSON)')
    
    args = parser.parse_args()
    
    # Parse db_config se fornecido
    db_config = None
    if args.db_config:
        try:
            db_config = json.loads(args.db_config)
        except json.JSONDecodeError:
            print(json.dumps({
                "success": False,
                "error": "db_config inválido (deve ser JSON)"
            }, ensure_ascii=False, indent=2))
            sys.exit(1)
    
    # Inicializa Smart Router
    try:
        router = SmartRouter(db_config)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": f"Erro ao inicializar Smart Router: {str(e)}"
        }, ensure_ascii=False, indent=2))
        sys.exit(1)
    
    # Executa roteamento
    result = router.route(
        query=args.query,
        document_id=args.document_id,
        top_k=args.top_k,
        threshold=args.threshold,
        include_answer=not args.no_llm,
        strictness=args.strictness,
        mode=args.mode,
        format=args.format,
        length=args.length,
        citations=args.citations,
        use_full_document=args.use_full_document,
        db_config=db_config
    )
    
    # Retorna resultado em JSON
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()

