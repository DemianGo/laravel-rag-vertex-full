#!/usr/bin/env python3
"""
Módulo de busca Full-Text Search (FTS) como fallback para busca vetorial
Implementa busca textual eficiente quando embeddings não estão disponíveis
"""

import sys
import logging
from typing import List, Dict, Optional, Any
from database import DatabaseManager

logger = logging.getLogger(__name__)

class FTSSearchEngine:
    """Motor de busca Full-Text Search para fallback quando embeddings não estão disponíveis"""
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        """
        Inicializa o motor de busca FTS
        
        Args:
            db_config: Configuração do banco de dados
        """
        self.db_manager = DatabaseManager(db_config)
    
    def search(
        self,
        query: str,
        document_id: Optional[int] = None,
        top_k: int = 5,
        threshold: float = 0.1
    ) -> Dict[str, Any]:
        """
        Busca textual usando Full-Text Search
        
        Args:
            query: Query de busca
            document_id: ID do documento para filtrar (opcional)
            top_k: Número de resultados a retornar
            threshold: Threshold mínimo (não usado em FTS, mas mantido para compatibilidade)
            
        Returns:
            Lista de chunks encontrados com score de relevância
        """
        try:
            # Tokenizar query para busca FTS
            tokens = self._tokenize_query(query)
            if not tokens:
                logger.warning("Query vazia após tokenização")
                return {'chunks': [], 'execution_time': 0}
            
            # Construir query SQL para FTS
            if self.db_manager.is_pgsql():
                # PostgreSQL com FTS
                return self._search_pgsql_fts(query, tokens, document_id, top_k)
            else:
                # MySQL com LIKE
                return self._search_like(query, tokens, document_id, top_k)
                
        except Exception as e:
            logger.error(f"Erro na busca FTS: {str(e)}")
            return {'chunks': [], 'execution_time': 0}
    
    def _tokenize_query(self, query: str) -> List[str]:
        """
        Tokeniza a query para busca FTS
        
        Args:
            query: Query original
            
        Returns:
            Lista de tokens limpos
        """
        if not query:
            return []
        
        # Limpar e tokenizar
        import re
        
        # Converter para minúsculas e remover caracteres especiais
        clean_query = re.sub(r'[^\w\s]', ' ', query.lower())
        
        # Dividir em palavras e remover espaços vazios
        tokens = [token.strip() for token in clean_query.split() if token.strip()]
        
        # Filtrar palavras muito curtas (menos de 2 caracteres), exceto números
        tokens = [token for token in tokens if len(token) >= 2 or token.isdigit()]
        
        # Remover stop words básicas em português
        stop_words = {
            'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos',
            'para', 'por', 'com', 'sem', 'sobre', 'entre', 'através', 'durante',
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas',
            'que', 'quem', 'onde', 'quando', 'como', 'porque', 'se', 'mas',
            'e', 'ou', 'nem', 'já', 'ainda', 'também', 'muito', 'pouco',
            'mais', 'menos', 'maior', 'menor', 'melhor', 'pior'
        }
        
        tokens = [token for token in tokens if token not in stop_words]
        
        return tokens
    
    def _search_pgsql_fts(self, query: str, tokens: List[str], document_id: Optional[int], top_k: int) -> List[Dict[str, Any]]:
        """
        Busca FTS no PostgreSQL
        
        Args:
            query: Query original
            tokens: Tokens tokenizados
            document_id: ID do documento (opcional)
            top_k: Número de resultados
            
        Returns:
            Lista de chunks encontrados
        """
        try:
            # Construir query FTS mais flexível - tentar múltiplas estratégias
            strategies = []
            
            if len(tokens) <= 3:
                # Para queries curtas, usar AND
                strategies.append(' & '.join(tokens))
            else:
                # Para queries longas, tentar diferentes estratégias
                # 1. Palavras mais importantes (sem números)
                important_words = [t for t in tokens if not t.isdigit() and len(t) > 3]
                if important_words:
                    strategies.append(' & '.join(important_words))
                
                # 2. Primeiras palavras importantes
                if len(important_words) >= 2:
                    strategies.append(' & '.join(important_words[:2]))
                
                # 3. OR entre todas as palavras
                strategies.append(' | '.join(tokens))
            
            # Tentar cada estratégia até encontrar resultados
            for fts_query in strategies:
                # Query SQL para PostgreSQL FTS
                sql = """
                    SELECT 
                        c.id,
                        c.content,
                        c.document_id,
                        c.ord,
                        ts_rank(
                            to_tsvector('portuguese', c.content),
                            plainto_tsquery('portuguese', %s)
                        ) as rank
                    FROM chunks c
                    WHERE to_tsvector('portuguese', c.content) @@ plainto_tsquery('portuguese', %s)
                """
                
                params = [fts_query, fts_query]
                
                # Adicionar filtro por documento se especificado
                if document_id:
                    sql += " AND c.document_id = %s"
                    params.append(document_id)
                
                # Ordenar por relevância e limitar resultados
                sql += " ORDER BY rank DESC LIMIT %s"
                params.append(top_k)
                
                # Executar query
                results = self.db_manager.execute_query(sql, params)
                if results:
                    # Converter para formato esperado
                    chunks = []
                    for row in results:
                        chunks.append({
                            'id': row['id'],
                            'content': row['content'],
                            'document_id': row['document_id'],
                            'ord': row['ord'],
                            'similarity': float(row['rank'])
                        })
                    return {'chunks': chunks, 'execution_time': 0}
            
            # Se nenhuma estratégia funcionou, retornar vazio
            return {'chunks': [], 'execution_time': 0}
            
        except Exception as e:
            logger.warning(f"FTS PostgreSQL falhou: {str(e)}, tentando LIKE")
            return self._search_like(query, tokens, document_id, top_k)
    
    def _search_like(self, query: str, tokens: List[str], document_id: Optional[int], top_k: int) -> List[Dict[str, Any]]:
        """
        Busca com LIKE para MySQL (fallback)
        
        Args:
            query: Query original
            tokens: Tokens tokenizados
            document_id: ID do documento (opcional)
            top_k: Número de resultados
            
        Returns:
            Lista de chunks encontrados
        """
        try:
            if not tokens:
                return []
            
            # Construir query LIKE
            like_conditions = []
            params = []
            
            # Cada token deve estar presente no conteúdo
            for token in tokens:
                like_conditions.append("LOWER(c.content) LIKE LOWER(%s)")
                params.append(f"%{token}%")
            
            # Query SQL base
            sql = """
                SELECT 
                    c.id,
                    c.content,
                    c.document_id,
                    c.ord,
                    LENGTH(c.content) as content_length
                FROM chunks c
                WHERE {}
            """.format(" AND ".join(like_conditions))
            
            # Adicionar filtro por documento se especificado
            if document_id:
                sql += " AND c.document_id = %s"
                params.append(document_id)
            
            # Ordenar por tamanho do conteúdo (heurística de relevância)
            sql += " ORDER BY content_length DESC LIMIT %s"
            params.append(top_k)
            
            # Executar query
            results = self.db_manager.execute_query(sql, params)
            
            # Converter para formato padrão
            chunks = []
            for row in results:
                # Calcular score baseado no número de tokens encontrados
                content_lower = row['content'].lower()
                matches = sum(1 for token in tokens if token in content_lower)
                score = matches / len(tokens) if tokens else 0
                
                chunks.append({
                    'id': row['id'],
                    'content': row['content'],
                    'document_id': row['document_id'],
                    'ord': row['ord'],
                    'similarity': score
                })
            
            return {'chunks': chunks, 'execution_time': 0}
            
        except Exception as e:
            logger.error(f"Erro na busca LIKE: {str(e)}")
            return {'chunks': [], 'execution_time': 0}
    
    def is_available(self) -> bool:
        """
        Verifica se o motor FTS está disponível
        
        Returns:
            True se disponível, False caso contrário
        """
        try:
            # Teste simples de conectividade
            test_results = self.db_manager.execute_query("SELECT 1", [])
            return len(test_results) > 0
        except Exception:
            return False
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Retorna estatísticas do motor FTS
        
        Returns:
            Dict com estatísticas
        """
        try:
            # Contar chunks disponíveis para busca
            total_chunks = self.db_manager.execute_query(
                "SELECT COUNT(*) as count FROM chunks", []
            )[0]['count']
            
            return {
                'total_chunks': total_chunks,
                'database_type': 'postgresql' if self.db_manager.is_pgsql() else 'other',
                'fts_available': self.is_available()
            }
        except Exception as e:
            return {
                'error': str(e),
                'fts_available': False
            }
