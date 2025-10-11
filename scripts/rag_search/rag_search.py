#!/usr/bin/env python3
"""
Sistema de Busca RAG em Python para Laravel
ARQUIVO CORRIGIDO - Substitui o rag_search.py original
"""

import sys
import json
import argparse
import os
from pathlib import Path
from typing import List, Dict, Any, Optional
import time

# Fix para imports - adiciona diretório do script ao path
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

# Agora importa os módulos locais
try:
    import embeddings_service
    import vector_search
    import fts_search
    import llm_service
    import database
    from config import Config
    from mode_detector import ModeDetector, ParameterNormalizer
    from extractors import ContentExtractors
    from formatters import ResponseFormatters
    from guards import ResponseGuards
except ImportError as e:
    # Retorna erro em JSON para o Laravel processar
    error_response = {
        "success": False,
        "error": f"Erro ao importar módulos: {str(e)}. Verifique se todos os arquivos estão em {SCRIPT_DIR}",
        "query": "",
        "chunks": [],
        "answer": "",
        "metadata": {
            "script_dir": str(SCRIPT_DIR),
            "python_path": sys.path[:3]
        }
    }
    print(json.dumps(error_response, ensure_ascii=False, indent=2))
    sys.exit(1)


class RAGSearchSystem:
    """Sistema completo de busca RAG"""
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        """
        Inicializa sistema RAG
        
        Args:
            db_config: Configuração do banco (host, database, user, password)
        """
        try:
            if db_config:
                self.config = Config(db_config)
            else:
                self.config = Config
            self.embeddings = embeddings_service.EmbeddingsService(self.config)
            self.vector_search = vector_search.VectorSearchEngine(db_config)
            self.fts_search = fts_search.FTSSearchEngine(db_config)
            self.llm = llm_service.LLMService(self.config)
        except Exception as e:
            raise Exception(f"Erro ao inicializar RAG: {str(e)}")
        
    def search(
        self,
        query: str,
        document_id: Optional[int] = None,
        top_k: int = 5,
        similarity_threshold: float = 0.3,
        include_answer: bool = True,
        strictness: int = 2,
        mode: str = "auto",
        format: str = "plain",
        length: str = "auto",
        citations: int = 0,
        use_full_document: bool = False
    ) -> Dict[str, Any]:
        """
        Busca RAG completa com todos os modos de resposta
        
        Args:
            query: Pergunta do usuário
            document_id: ID do documento para filtrar (obrigatório)
            top_k: Número de chunks a retornar (1-30)
            similarity_threshold: Threshold de similaridade (0-1)
            include_answer: Se deve gerar resposta com LLM
            strictness: Nível de rigor (0-3). Se 3, pula LLM conforme PROJECT_README.md
            mode: Modo de resposta (auto/direct/summary/quote/list/table/document_full)
            format: Formato de saída (plain/markdown/html)
            length: Comprimento da resposta (auto/short/medium/long/xl)
            citations: Número de citações (0-10)
            use_full_document: Se true, usa TODO o documento (ignora busca por query)
            
        Returns:
            Dict com chunks encontrados e resposta gerada conforme contrato PROJECT_README.md
        """
        start_time = time.time()
        
        try:
            # 1. Validação de parâmetros
            validation = ResponseGuards.validate_input_parameters(query, document_id, top_k, citations, mode)
            if not validation['valid']:
                return {
                    'success': False,
                    'error': '; '.join(validation['errors']),
                    'metadata': {'validation_failed': True}
                }
            
            # 2. Normalização de parâmetros
            mode = ModeDetector.detect_mode(mode, query)
            format_type = ParameterNormalizer.normalize_format(format)
            length = ParameterNormalizer.normalize_length(length)
            top_k = ParameterNormalizer.validate_top_k(top_k)
            citations = ParameterNormalizer.validate_citations(citations)
            
            # 3. Verificar se o documento tem embeddings
            document_has_embeddings = False
            if document_id:
                try:
                    chunks_with_embeddings = self.vector_search.db_manager.execute_query(
                        "SELECT COUNT(*) as count FROM chunks WHERE document_id = %s AND embedding IS NOT NULL",
                        [document_id]
                    )
                    document_has_embeddings = chunks_with_embeddings[0]['count'] > 0 if chunks_with_embeddings else False
                except Exception:
                    document_has_embeddings = False
            
            # 3.5. Modo DOCUMENT_FULL ou USE_FULL_DOCUMENT
            if mode == 'document_full' or use_full_document:
                if document_id:
                    print(f"[DEBUG] Modo document_full/use_full_document ativado para documento {document_id}", file=sys.stderr)
                    
                    # Carregar TODOS os chunks do documento específico
                    try:
                        all_chunks_query = "SELECT id, content, document_id, ord FROM chunks WHERE document_id = %s ORDER BY ord ASC"
                        all_chunks_result = self.vector_search.db_manager.execute_query(all_chunks_query, [document_id])
                    except Exception as e:
                        return {
                            "ok": False,
                            "error": f"Erro ao carregar documento completo: {str(e)}",
                            "metadata": {"error_type": "database_error"}
                        }
                else:
                    print(f"[DEBUG] Modo document_full/use_full_document ativado para TODOS os documentos", file=sys.stderr)
                    
                    # Carregar TODOS os chunks de TODOS os documentos
                    try:
                        all_chunks_query = "SELECT id, content, document_id, ord FROM chunks ORDER BY document_id, ord ASC"
                        all_chunks_result = self.vector_search.db_manager.execute_query(all_chunks_query)
                    except Exception as e:
                        return {
                            "ok": False,
                            "error": f"Erro ao carregar todos os documentos: {str(e)}",
                            "metadata": {"error_type": "database_error"}
                        }
                
                # Processar resultados (tanto documento específico quanto todos)
                if all_chunks_result:
                    chunks = []
                    for row in all_chunks_result:
                        chunks.append({
                            'id': row['id'],
                            'content': row['content'],
                            'document_id': row['document_id'],
                            'ord': row['ord'],
                            'similarity': 1.0  # Máxima similaridade para chunks do documento completo
                        })
                    search_method = "document_full"
                    if document_id:
                        print(f"[DEBUG] Carregados {len(chunks)} chunks do documento {document_id}", file=sys.stderr)
                    else:
                        print(f"[DEBUG] Carregados {len(chunks)} chunks de todos os documentos", file=sys.stderr)
                else:
                    if document_id:
                        error_msg = f"Nenhum chunk encontrado para o documento {document_id}"
                    else:
                        error_msg = "Nenhum chunk encontrado em nenhum documento"
                    return {
                        "ok": False,
                        "error": error_msg,
                        "metadata": {"error_type": "no_chunks_found"}
                    }
            else:
                # 4. Busca de chunks (híbrida: vector + FTS) - comportamento normal
                chunks = []
                search_method = "unknown"
                
                if document_id and not document_has_embeddings:
                    print(f"[DEBUG] Documento {document_id} não tem embeddings, usando FTS diretamente", file=sys.stderr)
                    chunks = self.fts_search.search(
                        query=query,
                        document_id=document_id,
                        top_k=top_k,
                        threshold=0.1
                    )
                    search_method = "fts_direct"
                else:
                    # Buscar chunks similares com embeddings
                    chunks = self.vector_search.search(
                        query=query,
                        document_id=document_id,
                        top_k=top_k,
                        threshold=float(similarity_threshold)
                    )
                    search_method = "vector_search"
            
                    # Se não encontrou nada com busca vetorial, tentar FTS como fallback
                    if not chunks:
                        print(f"[DEBUG] Busca vetorial não encontrou resultados, tentando FTS fallback", file=sys.stderr)
                        try:
                            chunks = self.fts_search.search(
                                query=query,
                                document_id=document_id,
                                top_k=top_k,
                                threshold=0.1
                            )
                            search_method = "fts_fallback"
                            print(f"[DEBUG] FTS fallback encontrou {len(chunks)} chunks", file=sys.stderr)
                        except Exception as e:
                            print(f"[DEBUG] Erro no FTS fallback: {str(e)}", file=sys.stderr)
                
                    # 4.1. Fallback inteligente se não encontrou chunks (só para busca normal)
                    if not chunks:
                        print(f"[DEBUG] Nenhum chunk encontrado, tentando busca inteligente", file=sys.stderr)
                        # Tentar queries mais simples baseadas na query original
                        fallback_queries = self._generate_fallback_queries(query)
                        for fallback_query in fallback_queries:
                            try:
                                chunks = self.fts_search.search(
                                    query=fallback_query,
                                    document_id=document_id,
                                    top_k=top_k,
                                    threshold=0.1
                                )
                                if chunks:
                                    search_method = "fts_fallback_intelligent"
                                    print(f"[DEBUG] Busca inteligente com '{fallback_query}' encontrou {len(chunks)} chunks", file=sys.stderr)
                                    break
                            except Exception as e:
                                print(f"[DEBUG] Erro na busca inteligente: {str(e)}", file=sys.stderr)
            
            # 5. Guard para QUOTE: nunca retorna vazio
            if mode == 'quote':
                quote_guard = ResponseGuards.guard_quote(chunks, query, format_type)
                if quote_guard:
                    return quote_guard
            
            # 7. Processamento por modo
            answer = None
            llm_provider = None
            sources = []
            used_chunks = []
            
            # Encontrar melhor chunk
            best_chunk = ContentExtractors.find_best_chunk(chunks)
            best_ord = ContentExtractors.get_best_ord(chunks)
            
            # Combinar chunks para contexto
            combined = ContentExtractors.combine_chunks(chunks)
            
            if mode == 'document_full':
                # Modo DOCUMENT_FULL - análise completa do documento
                print(f"[DEBUG] Processando modo document_full com {len(chunks)} chunks", file=sys.stderr)
                
                # Combinar TODO o conteúdo do documento
                all_content = ContentExtractors.combine_chunks(chunks)
                
                # Para document_full, sempre usar LLM para análise completa
                if include_answer and strictness < 3:
                    try:
                        # Prompt para análise completa do documento
                        prompt = f"""
                        Analise completamente o seguinte documento e responda à pergunta do usuário com base em TODO o conteúdo disponível:

                        PERGUNTA: {query}

                        CONTEÚDO COMPLETO DO DOCUMENTO:
                        {all_content}

                        INSTRUÇÕES:
                        - Use TODO o conteúdo do documento para responder
                        - Se for uma lista, extraia TODOS os itens numerados
                        - Se for um resumo, cubra TODOS os pontos principais
                        - Se for uma pergunta específica, use TODA a informação relevante
                        - Seja abrangente e completo na sua resposta
                        """
                        
                        if format_type == 'markdown':
                            prompt += "\n- Formate a resposta em Markdown"
                        elif format_type == 'html':
                            prompt += "\n- Formate a resposta em HTML"
                        
                        answer, llm_provider = self.llm.generate_answer(prompt, all_content)
                        print(f"[DEBUG] LLM analysis completa gerada com provider: {llm_provider}", file=sys.stderr)
                    except Exception as e:
                        print(f"[DEBUG] Erro na análise completa com LLM: {str(e)}", file=sys.stderr)
                        # Fallback: usar extração inteligente
                        answer = f"Análise do documento completo:\n\n{all_content[:1000]}{'...' if len(all_content) > 1000 else ''}"
                else:
                    # Sem LLM: retornar conteúdo combinado
                    answer = f"Conteúdo completo do documento:\n\n{all_content}"
                
                used_chunks = [chunk.get('id') for chunk in chunks]
                
            elif mode == 'list' or (mode == 'auto' and ModeDetector.has_list_intent(query)):
                # Modo LIST
                all_chunks = ContentExtractors.load_all_chunks(chunks)
                combined_list = ContentExtractors.combine_chunks_for_list(all_chunks)
                items = ContentExtractors.extract_numbered_items(combined_list)
                items = ResponseGuards.guard_list_items(items, combined_list)
                
                # Converter para formato de bullets
                bullets = [{'text': f"{num}. {text}"} for num, text in items.items()]
                
                # LLM opcional para refinar bullets
                if include_answer and strictness < 3:
                    try:
                        for bullet in bullets:
                            text = bullet['text']
                            refined = self.llm.rewrite_line(text, format_type)
                            if refined:
                                bullet['text'] = refined
                        llm_provider = self.llm.get_current_provider()
                    except Exception as e:
                        print(f"[DEBUG] Erro ao refinar bullets: {str(e)}", file=sys.stderr)
                
                answer = ResponseFormatters.render_bullets_simple(bullets, format_type)
                used_chunks = [chunk.get('id') for chunk in chunks]
                
            elif mode == 'table' or (mode == 'auto' and ModeDetector.has_table_intent(query)):
                # Modo TABLE
                neighbors = ContentExtractors.load_ord_window(chunks, max(0, best_ord - 2), best_ord + 30)
                combined_table = ContentExtractors.combine_chunks(neighbors)
                pairs = ContentExtractors.extract_key_value_pairs(combined_table)
                pairs = ResponseGuards.guard_table_pairs(pairs, combined_table)
                
                # LLM opcional para refinar pares
                if include_answer and strictness < 3:
                    try:
                        refined_pairs = {}
                        for key, value in pairs.items():
                            refined_key = self.llm.rewrite_line(key, format_type)
                            refined_value = self.llm.rewrite_line(value, format_type)
                            refined_pairs[refined_key or key] = refined_value or value
                        pairs = refined_pairs
                        llm_provider = self.llm.get_current_provider()
                    except Exception as e:
                        print(f"[DEBUG] Erro ao refinar pares: {str(e)}", file=sys.stderr)
                
                answer = ResponseFormatters.render_table(pairs, format_type)
                used_chunks = [chunk.get('id') for chunk in chunks]
                
            elif mode == 'quote' or (mode == 'auto' and ModeDetector.has_quote_intent(query)):
                # Modo QUOTE
                quote = ContentExtractors.extract_quote(combined)
                if not quote:
                    # Fallback: melhor sentença
                    sentences = combined.split('. ')
                    quote = sentences[0] if sentences else 'Sem citação disponível no contexto.'
                
                quote = ResponseFormatters.ensure_double_quoted(quote)
                answer = ResponseFormatters.render_quote(quote, format_type)
                used_chunks = [chunk.get('id') for chunk in chunks]
                
            elif mode == 'summary' or (mode == 'auto' and ModeDetector.has_summary_intent(query)):
                # Modo SUMMARY - Inteligente para queries genéricas
                if not chunks:
                    print(f"[DEBUG] Modo summary sem chunks, tentando busca inteligente", file=sys.stderr)
                    # Para queries genéricas como "Faça um sumário", tentar palavras-chave do documento
                    intelligent_queries = self._generate_intelligent_queries(query, document_id)
                    for int_query in intelligent_queries:
                        print(f"[DEBUG] Tentando query inteligente: '{int_query}'", file=sys.stderr)
                        if document_id:
                            # Buscar com FTS usando query inteligente
                            try:
                                chunks = self.fts_search.search(
                                    query=int_query,
                                    document_id=document_id,
                                    top_k=top_k,
                                    threshold=0.1
                                )
                                if chunks:
                                    print(f"[DEBUG] Query inteligente '{int_query}' encontrou {len(chunks)} chunks", file=sys.stderr)
                                    search_method = "fts_intelligent"
                                    break
                            except Exception as e:
                                print(f"[DEBUG] Erro na busca inteligente: {str(e)}", file=sys.stderr)
                
                if chunks:
                    neighbors = ContentExtractors.load_ord_window(chunks, max(0, best_ord - 2), best_ord + 40)
                    combined_summary = ContentExtractors.combine_chunks(neighbors)
                else:
                    # Se ainda não tem chunks, usar fallback do guard
                    combined_summary = ""
                
                # Extrair bullets de resumo
                bullets = [{'text': sentence.strip()} for sentence in combined_summary.split('.') if sentence.strip()]
                
                # LLM opcional para refinar bullets
                if include_answer and strictness < 3:
                    try:
                        for bullet in bullets:
                            text = bullet['text']
                            refined = self.llm.rewrite_line(text, format_type)
                            if refined:
                                bullet['text'] = refined
                        llm_provider = self.llm.get_current_provider()
                    except Exception as e:
                        print(f"[DEBUG] Erro ao refinar summary: {str(e)}", file=sys.stderr)
                
                # Aplicar guard do summary
                fallback_summary = ResponseGuards.guard_summary(bullets, neighbors, query)
                if fallback_summary:
                    answer = fallback_summary
                else:
                    answer = ResponseFormatters.render_bullets_simple(bullets, format_type)
                
                used_chunks = [chunk.get('id') for chunk in chunks]
                
            else:
                # Modo DIRECT
                if include_answer and strictness < 3:
                    try:
                        directive = ResponseFormatters.build_direct_rewrite_directive(strictness)
                        context = "\n\n".join([chunk["content"] for chunk in chunks[:3]])
                        answer, llm_provider = self.llm.generate_answer(directive + " " + query, context)
                    except Exception as e:
                        print(f"[DEBUG] Erro no LLM direct: {str(e)}", file=sys.stderr)
                        answer = combined
                else:
                    answer = combined
                
                used_chunks = [chunk.get('id') for chunk in chunks]
            
            # Construir sources
            sources = [f"chunk#{chunk_id}" for chunk_id in used_chunks]
            
            # 8. Retornar resultado conforme contrato PROJECT_README.md
            processing_time = round(time.time() - start_time, 3)
            
            # Formatar resposta final
            final_answer = ResponseFormatters.format_as(answer or "Resposta não gerada (LLM desabilitado)", format_type)
            
            return {
                "ok": True,
                "query": query,
                    "top_k": top_k,
                "used_doc": document_id,
                "used_chunks": used_chunks,
                "chunks": chunks,  # Adicionar campo chunks na resposta
                "mode_used": mode,
                "format": format_type,
                "answer": final_answer,
                "sources": sources,
                "debug": {
                    "timings_ms": {
                        "retrieve": round(processing_time * 1000, 2),
                        "compose": round(processing_time * 1000, 2)
                    },
                    "cost": {
                        "embeddings_tokens": 0,  # TODO: implementar contagem
                        "generation_tokens": 0,  # TODO: implementar contagem
                        "cost_brl": 0.0  # TODO: implementar cálculo
                    },
                    "flags": {
                        "fallback_applied": search_method == "fts_fallback",
                        "strictness": strictness
                    },
                    "search_method": search_method,
                    "llm_used": include_answer and strictness < 3,
                    "llm_provider": llm_provider,
                    "processing_time": processing_time
                }
            }
            
        except Exception as e:
            return {
                "success": False,
                "error": f"Erro na busca RAG: {str(e)}",
                "query": query,
                "chunks": [],
                "answer": "",
                "metadata": {
                    "processing_time": round(time.time() - start_time, 3)
                }
            }
    
    def _generate_fallback_queries(self, query: str) -> List[str]:
        """
        Gera queries de fallback mais simples baseadas na query original
        
        Args:
            query: Query original
            
        Returns:
            Lista de queries de fallback ordenadas por relevância
        """
        import re
        
        # Tokenizar query
        words = re.findall(r'\b\w+\b', query.lower())
        
        # Remover stop words comuns
        stop_words = {
            'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos',
            'para', 'por', 'com', 'sem', 'sobre', 'entre', 'através', 'durante',
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas',
            'que', 'quem', 'onde', 'quando', 'como', 'porque', 'se', 'mas',
            'e', 'ou', 'nem', 'já', 'ainda', 'também', 'muito', 'pouco',
            'mais', 'menos', 'maior', 'menor', 'melhor', 'pior',
            'no', 'na', 'nos', 'nas', 'do', 'da', 'dos', 'das',
            'liste', 'listar', 'mostre', 'mostrar', 'descreva', 'descrever',
            'arquivo', 'documento', 'texto', 'conteúdo'
        }
        
        # Filtrar palavras importantes
        important_words = [w for w in words if w not in stop_words and len(w) > 2]
        
        # Gerar queries de fallback
        fallback_queries = []
        
        # 1. Palavras mais importantes (sem números)
        non_numeric = [w for w in important_words if not w.isdigit()]
        if non_numeric:
            fallback_queries.append(' '.join(non_numeric))
        
        # 2. Primeiras 2 palavras importantes
        if len(important_words) >= 2:
            fallback_queries.append(' '.join(important_words[:2]))
        
        # 3. Apenas números (se houver)
        numbers = [w for w in important_words if w.isdigit()]
        if numbers:
            fallback_queries.append(' '.join(numbers))
        
        # 4. Palavras individuais importantes
        for word in important_words[:3]:  # Apenas as 3 primeiras
            fallback_queries.append(word)
        
        # 5. Queries genéricas baseadas em padrões comuns
        if any(word in query.lower() for word in ['motivo', 'motivos', 'razão', 'razões']):
            fallback_queries.extend(['motivos', 'razões', 'lista'])
        
        if any(word in query.lower() for word in ['30', 'vinte', 'trinta']):
            fallback_queries.extend(['30', 'trinta'])
        
        # Remover duplicatas mantendo ordem
        seen = set()
        unique_queries = []
        for q in fallback_queries:
            if q not in seen:
                seen.add(q)
                unique_queries.append(q)
        
        return unique_queries

    def _generate_intelligent_queries(self, query: str, document_id: Optional[int]) -> List[str]:
        """
        Gera queries inteligentes para modo summary quando não encontra chunks
        
        Args:
            query: Query original
            document_id: ID do documento (para buscar palavras-chave específicas)
            
        Returns:
            Lista de queries inteligentes ordenadas por relevância
        """
        intelligent_queries = []
        
        # 1. Queries baseadas no título do documento
        if document_id:
            try:
                doc_info = self.vector_search.db_manager.execute_query(
                    "SELECT title FROM documents WHERE id = %s", [document_id]
                )
                if doc_info:
                    title = doc_info[0]['title'].lower()
                    # Extrair palavras-chave do título
                    import re
                    title_words = re.findall(r'\b\w+\b', title)
                    # Adicionar palavras importantes do título
                    for word in title_words:
                        if len(word) > 3 and word not in ['para', 'escolher', 'linha', 'prática']:
                            intelligent_queries.append(word)
            except Exception as e:
                print(f"[DEBUG] Erro ao buscar título do documento: {str(e)}", file=sys.stderr)
        
        # 2. Queries genéricas baseadas no tipo de documento
        if any(word in query.lower() for word in ['sumário', 'resumo', 'resuma', 'faça']):
            intelligent_queries.extend(['motivos', 'certificado', 'qualidade', 'produto', 'linha'])
        
        # 3. Queries específicas para documentos REUNI
        if document_id and 'reuni' in str(document_id).lower():
            intelligent_queries.extend(['REUNI', 'canabinóides', 'cannabis', 'medicinal', 'certificado'])
        
        # 4. Queries baseadas em padrões comuns
        if any(word in query.lower() for word in ['motivo', 'motivos']):
            intelligent_queries.extend(['30', 'trinta', 'motivos', 'razões'])
        
        # 5. Queries genéricas de qualidade
        intelligent_queries.extend(['qualidade', 'certificado', 'análise', 'lote', 'estabilidade'])
        
        # Remover duplicatas mantendo ordem
        seen = set()
        unique_queries = []
        for q in intelligent_queries:
            if q.lower() not in seen:
                seen.add(q.lower())
                unique_queries.append(q)
        
        return unique_queries[:5]  # Retornar apenas as 5 melhores


def parse_args():
    """Parse argumentos da linha de comando"""
    parser = argparse.ArgumentParser(
        description="Sistema de Busca RAG em Python",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemplos:
  python rag_search.py --query "O que é REUNI?" --document-id 1 --top-k 5
  python rag_search.py --query "resumo" --top-k 10 --no-llm
  python rag_search.py --query "certificado" --document-id 5 --threshold 0.5
        """
    )
    
    parser.add_argument(
        '--query',
        required=True,
        help='Pergunta/query do usuário'
    )
    
    parser.add_argument(
        '--document-id',
        type=int,
        default=None,
        help='ID do documento para filtrar busca (opcional)'
    )
    
    parser.add_argument(
        '--top-k',
        type=int,
        default=5,
        help='Número de chunks a retornar (padrão: 5)'
    )
    
    parser.add_argument(
        '--threshold',
        type=float,
        default=0.3,
        help='Threshold de similaridade 0-1 (padrão: 0.3)'
    )
    
    parser.add_argument(
        '--no-llm',
        action='store_true',
        help='Não gerar resposta com LLM (apenas retorna chunks)'
    )
    
    parser.add_argument(
        '--strictness',
        type=int,
        default=2,
        choices=[0, 1, 2, 3],
        help='Nível de rigor (0-3). Se 3, pula LLM conforme PROJECT_README.md (padrão: 2)'
    )
    
    parser.add_argument(
        '--mode',
        default='auto',
        choices=['auto', 'direct', 'summary', 'quote', 'list', 'table', 'document_full'],
        help='Modo de resposta (padrão: auto)'
    )
    
    parser.add_argument(
        '--format',
        default='plain',
        choices=['plain', 'markdown', 'html'],
        help='Formato de saída (padrão: plain)'
    )
    
    parser.add_argument(
        '--length',
        default='auto',
        choices=['auto', 'short', 'medium', 'long', 'xl'],
        help='Comprimento da resposta (padrão: auto)'
    )
    
    parser.add_argument(
        '--citations',
        type=int,
        default=0,
        help='Número de citações (0-10, padrão: 0)'
    )
    
    parser.add_argument(
        '--use-full-document',
        action='store_true',
        default=False,
        help='Usar TODO o documento (ignora busca por query)'
    )
    
    parser.add_argument(
        '--db-config',
        type=str,
        default=None,
        help='JSON com configuração do banco (opcional)'
    )
    
    parser.add_argument(
        '--verbose',
        action='store_true',
        help='Modo verbose (mostra logs de debug)'
    )
    
    return parser.parse_args()


def main():
    """Função principal CLI"""
    args = parse_args()
    
    try:
        # Parse configuração do banco se fornecida
        db_config = None
        if args.db_config:
            db_config = json.loads(args.db_config)
        
        # Inicializar sistema RAG
        if args.verbose:
            print(f"[DEBUG] Inicializando RAG...", file=sys.stderr)
            print(f"[DEBUG] Query: {args.query}", file=sys.stderr)
            print(f"[DEBUG] Document ID: {args.document_id}", file=sys.stderr)
        
        rag = RAGSearchSystem(db_config)
        
        # Executar busca
        result = rag.search(
            query=args.query,
            document_id=args.document_id,
            top_k=args.top_k,
            similarity_threshold=args.threshold,
            include_answer=not args.no_llm,
            strictness=args.strictness,
            mode=args.mode,
            format=args.format,
            length=args.length,
            citations=args.citations,
            use_full_document=args.use_full_document
        )
        
        # Output JSON para stdout (Laravel vai capturar isso)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        
        # Exit code baseado em sucesso (novo formato usa 'ok')
        sys.exit(0 if result.get("ok", result.get("success", False)) else 1)
        
    except Exception as e:
        # Erro fatal - retorna JSON de erro
        error_result = {
            "success": False,
            "error": f"Erro fatal: {str(e)}",
            "query": args.query if hasattr(args, 'query') else "",
            "chunks": [],
            "answer": "",
            "metadata": {}
        }
        print(json.dumps(error_result, ensure_ascii=False, indent=2))
        sys.exit(1)


if __name__ == "__main__":
    main()