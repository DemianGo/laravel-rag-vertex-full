#!/usr/bin/env python3
"""
Serviço de Busca Híbrida Inteligente com Grounding do Gemini
"""

import logging
import json
import time
from typing import Dict, Any, Optional, List, Tuple
from config import Config
from llm_service import LLMService
from vector_search import VectorSearchEngine
from fts_search import FTSSearchEngine

logger = logging.getLogger(__name__)

class HybridSearchService:
    """Serviço de busca híbrida inteligente com grounding do Gemini"""
    
    def __init__(self, config=None, llm_provider=None):
        self.config = config or Config()
        self.llm_provider = llm_provider or getattr(self.config, 'DEFAULT_LLM_PROVIDER', 'gemini')
        self.llm_service = LLMService(self.config, provider=self.llm_provider)
        
        # Configuração do banco
        db_config = getattr(self.config, 'DB_CONFIG', None)
        if db_config:
            self.vector_search = VectorSearchEngine(db_config)
            self.fts_search = FTSSearchEngine(db_config)
        else:
            self.vector_search = VectorSearchEngine()
            self.fts_search = FTSSearchEngine()
        
        # Configurações do sistema híbrido
        self.require_confirmation = getattr(self.config, 'REQUIRE_GROUNDING_CONFIRMATION', False)
        self.grounding_cost_per_query = getattr(self.config, 'GROUNDING_COST_PER_QUERY', 0.01)
        
    def classify_query_type(self, query: str) -> str:
        """Classifica a pergunta do usuário em tipos pré-definidos."""
        query_lower = query.lower()
        
        if any(word in query_lower for word in ["nosso", "do contrato", "neste documento"]):
            return "document_specific"
        elif any(word in query_lower for word in ["o que é", "como funciona", "defina"]):
            return "conceptual"
        elif any(word in query_lower for word in ["compare", "versus", "mercado"]):
            return "comparative"
        else:
            return "other"
    
    def search_documents_only(self, query: str, document_id: Optional[int] = None, 
                             top_k: int = 5, threshold: float = 0.3) -> Dict[str, Any]:
        """Busca apenas em documentos locais."""
        try:
            # Busca vetorial
            vector_results = self.vector_search.search(query, document_id, top_k, threshold)
            
            # Corrigir formato se necessário
            if isinstance(vector_results, list):
                vector_results = {'chunks': vector_results}
            
            # Busca FTS como fallback
            if not vector_results.get('chunks'):
                fts_results = self.fts_search.search(query, document_id, top_k)
                if fts_results.get('chunks'):
                    vector_results = fts_results
            
            # Gera resposta com LLM
            if vector_results.get('chunks'):
                chunks = vector_results['chunks']
                context = "\n".join([chunk['content'] for chunk in chunks])
                
                prompt = f"""
                Baseado no contexto fornecido, responda à pergunta do usuário.
                
                Contexto:
                {context}
                
                Pergunta: {query}
                
                Responda de forma clara e precisa, baseando-se apenas no contexto fornecido.
                """
                
                answer = self.llm_service.generate_text(prompt)
                
                return {
                    'success': True,
                    'answer': answer,
                    'sources': {'documents': chunks, 'web': []},
                    'chunks_found': len(chunks),
                    'used_grounding': False,
                    'search_method': 'documents_only',
                    'execution_time': vector_results.get('execution_time', 0),
                    'llm_provider': self.llm_provider
                }
            else:
                return {
                    'success': False,
                    'error': 'Nenhum chunk relevante encontrado nos documentos',
                    'answer': '',
                    'sources': {'documents': [], 'web': []},
                    'chunks_found': 0,
                    'used_grounding': False,
                    'search_method': 'documents_only',
                    'execution_time': 0,
                    'llm_provider': self.llm_provider
                }
                
        except Exception as e:
            logger.error(f"Erro na busca em documentos: {e}")
            return {
                'success': False,
                'error': f'Erro na busca em documentos: {str(e)}',
                'answer': '',
                'sources': {'documents': [], 'web': []},
                'chunks_found': 0,
                'used_grounding': False,
                'search_method': 'documents_error',
                'execution_time': 0,
                'llm_provider': self.llm_provider
            }
    
    def search_with_grounding(self, query: str, document_id: Optional[int] = None, 
                             top_k: int = 5, threshold: float = 0.3) -> Dict[str, Any]:
        """Busca com grounding do Gemini (busca web + documentos)."""
        try:
            # Primeiro, busca em documentos locais
            doc_results = self.search_documents_only(query, document_id, top_k, threshold)
            
            # Se não encontrou nada nos documentos, usa grounding
            if not doc_results.get('chunks_found', 0) > 0:
                # Usa Gemini com grounding
                grounding_prompt = f"""
                Responda à seguinte pergunta usando informações da internet e conhecimento geral:
                
                Pergunta: {query}
                
                Forneça uma resposta completa e precisa.
                """
                
                # Chama Gemini com grounding
                grounding_result = self.llm_service.generate_with_grounding(grounding_prompt)
                
                if grounding_result.get('success'):
                    web_sources = grounding_result.get('grounding_metadata', {}).get('grounding_chunks', [])
                    
                    return {
                        'success': True,
                        'answer': grounding_result.get('answer', ''),
                        'sources': {'documents': [], 'web': web_sources},
                        'chunks_found': 0,
                        'used_grounding': True,
                        'search_method': 'grounding_only',
                        'execution_time': grounding_result.get('execution_time', 0),
                        'llm_provider': self.llm_provider
                    }
                else:
                    return {
                        'success': False,
                        'error': 'Erro na busca com grounding',
                        'answer': '',
                        'sources': {'documents': [], 'web': []},
                        'chunks_found': 0,
                        'used_grounding': False,
                        'search_method': 'grounding_error',
                        'execution_time': 0,
                        'llm_provider': self.llm_provider
                    }
            else:
                # Encontrou nos documentos, retorna resultado
                return doc_results
                
        except Exception as e:
            logger.error(f"Erro na busca com grounding: {e}")
            return {
                'success': False,
                'error': f'Erro na busca com grounding: {str(e)}',
                'answer': '',
                'sources': {'documents': [], 'web': []},
                'chunks_found': 0,
                'used_grounding': False,
                'search_method': 'grounding_error',
                'execution_time': 0,
                'llm_provider': self.llm_provider
            }
    
    def search(self, query: str, document_id: Optional[int] = None, 
               top_k: int = 5, threshold: float = 0.3, 
               force_grounding: bool = False) -> Dict[str, Any]:
        """Busca híbrida inteligente."""
        start_time = time.time()
        
        try:
            # Classifica o tipo de query
            query_type = self.classify_query_type(query)
            
            # Decide se deve usar grounding
            should_use_grounding = (
                force_grounding or 
                query_type in ['conceptual', 'comparative'] or
                'cep' in query.lower() or
                'cnpj' in query.lower() or
                'preço' in query.lower() or
                'cotação' in query.lower()
            )
            
            if should_use_grounding:
                result = self.search_with_grounding(query, document_id, top_k, threshold)
            else:
                result = self.search_documents_only(query, document_id, top_k, threshold)
            
            # Adiciona metadados
            execution_time = time.time() - start_time
            result['execution_time'] = execution_time
            result['query_type'] = query_type
            result['llm_provider'] = self.llm_provider
            
            return result
            
        except Exception as e:
            logger.error(f"Erro na busca híbrida: {e}")
            execution_time = time.time() - start_time
            return {
                'success': False,
                'error': f'Erro na busca híbrida: {str(e)}',
                'answer': '',
                'sources': {'documents': [], 'web': []},
                'chunks_found': 0,
                'used_grounding': False,
                'search_method': 'hybrid_error',
                'execution_time': execution_time,
                'llm_provider': self.llm_provider
            }
