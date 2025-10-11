import logging
import sys
from typing import List, Dict, Optional, Any
from database import DatabaseManager
from embeddings_service import EmbeddingsService
from config import Config

logger = logging.getLogger(__name__)

class VectorSearchEngine:
    """Motor de busca vetorial para chunks de documentos"""

    def __init__(self, db_config: Optional[Dict[str, Any]] = None):
        """
        Inicializa o motor de busca vetorial

        Args:
            db_config: Configuração personalizada do banco
        """
        if db_config:
            self.config = Config(db_config)
            self.db_manager = DatabaseManager(self.config.DB_CONFIG)
        else:
            self.config = Config
            self.db_manager = DatabaseManager(self.config.DB_CONFIG)
        self.embeddings_service = EmbeddingsService(self.config)

        # Verifica se os serviços estão disponíveis
        if not self.embeddings_service.is_available():
            logger.warning("Serviço de embeddings não está disponível")

        if not self.db_manager.test_connection():
            logger.error("Conexão com banco de dados falhou")

    def search(self, query: str, document_id: Optional[int] = None,
               top_k: int = None, threshold: float = None) -> List[Dict]:
        """
        Busca chunks similares à query

        Args:
            query: Texto da pergunta/busca
            document_id: ID do documento específico (None para buscar em todos)
            top_k: Número máximo de resultados (usa config padrão se None)
            threshold: Limite mínimo de similaridade (usa config padrão se None)

        Returns:
            Lista de chunks com suas similaridades, ordenados por relevância
        """
        if not query or not query.strip():
            return []

        # Usa valores padrão da configuração se não fornecidos
        top_k = top_k or (self.config.DEFAULT_TOP_K if hasattr(self.config, 'DEFAULT_TOP_K') else 5)
        threshold = threshold or (self.config.SIMILARITY_THRESHOLD if hasattr(self.config, 'SIMILARITY_THRESHOLD') else 0.3)

        try:
            # Gera embedding da query
            logger.info(f"Gerando embedding para query: {query[:50]}...")
            query_embedding = self.embeddings_service.encode(query)

            if query_embedding is None or len(query_embedding) == 0:
                logger.error("Falha ao gerar embedding da query")
                return []

            # Converte para lista Python para o PostgreSQL
            embedding_list = query_embedding.tolist()

            # Executa busca vetorial
            logger.info(f"Executando busca vetorial (top_k={top_k}, threshold={threshold})")

            # DEBUG: pré-visualização do SQL e parâmetros
            if document_id:
                sql_preview = (
                    "SELECT id, content, document_id, ord,\n"
                    "       1 - (embedding <=> %s::vector) AS similarity\n"
                    "FROM chunks\n"
                    "WHERE document_id = %s AND embedding IS NOT NULL\n"
                    "  AND (1 - (embedding <=> %s::vector)) >= %s\n"
                    "ORDER BY similarity DESC\n"
                    "LIMIT %s"
                )
                params_preview = (
                    f"[vector len={len(embedding_list)}]",
                    document_id,
                    f"[vector len={len(embedding_list)}]",
                    threshold,
                    top_k
                )
            else:
                sql_preview = (
                    "SELECT id, content, document_id, ord,\n"
                    "       1 - (embedding <=> %s::vector) AS similarity\n"
                    "FROM chunks\n"
                    "WHERE embedding IS NOT NULL\n"
                    "  AND (1 - (embedding <=> %s::vector)) >= %s\n"
                    "ORDER BY similarity DESC\n"
                    "LIMIT %s"
                )
                params_preview = (
                    f"[vector len={len(embedding_list)}]",
                    f"[vector len={len(embedding_list)}]",
                    threshold,
                    top_k
                )

            print("[DEBUG][VectorSearch] SQL:\n" + sql_preview, file=sys.stderr)
            print(f"[DEBUG][VectorSearch] Params: {params_preview}", file=sys.stderr)

            try:
                results = self.db_manager.execute_vector_search(
                    embedding_list, document_id, top_k, threshold
                )
            except Exception as e:
                print(f"[DEBUG][VectorSearch] Postgres error: {str(e)}", file=sys.stderr)
                raise

            logger.info(f"Encontrados {len(results)} chunks similares")
            print(f"[DEBUG][VectorSearch] results_count: {len(results)}", file=sys.stderr)
            # Fallback: se filtrado por documento e sem resultados, tenta busca global com threshold reduzido
            if document_id is not None and not results:
                fallback_threshold = min(0.05, float(threshold) if threshold is not None else 0.3)
                print(f"[DEBUG][VectorSearch] Fallback global search: threshold={fallback_threshold}", file=sys.stderr)
                try:
                    results_global = self.db_manager.execute_vector_search(
                        embedding_list, None, max(top_k, 5), fallback_threshold
                    )
                except Exception as e:
                    print(f"[DEBUG][VectorSearch] Fallback Postgres error: {str(e)}", file=sys.stderr)
                    results_global = []
                print(f"[DEBUG][VectorSearch] fallback_results_count: {len(results_global)}", file=sys.stderr)
                return results_global

            return results

        except Exception as e:
            logger.error(f"Erro na busca vetorial: {e}")
            return []

    def search_all_documents(self, query: str, top_k: int = None,
                           threshold: float = None) -> List[Dict]:
        """
        Busca chunks em todos os documentos

        Args:
            query: Texto da pergunta/busca
            top_k: Número máximo de resultados
            threshold: Limite mínimo de similaridade

        Returns:
            Lista de chunks de todos os documentos
        """
        return self.search(query, document_id=None, top_k=top_k, threshold=threshold)

    def search_by_document_ids(self, query: str, document_ids: List[int],
                              top_k: int = None, threshold: float = None) -> Dict[int, List[Dict]]:
        """
        Busca chunks em múltiplos documentos específicos

        Args:
            query: Texto da pergunta/busca
            document_ids: Lista de IDs dos documentos
            top_k: Número máximo de resultados por documento
            threshold: Limite mínimo de similaridade

        Returns:
            Dicionário com results por document_id
        """
        results = {}

        for doc_id in document_ids:
            try:
                doc_results = self.search(query, doc_id, top_k, threshold)
                if doc_results:
                    results[doc_id] = doc_results

            except Exception as e:
                logger.error(f"Erro ao buscar no documento {doc_id}: {e}")
                continue

        return results

    def get_similar_chunks(self, reference_chunk_id: int, top_k: int = None,
                          threshold: float = None) -> List[Dict]:
        """
        Encontra chunks similares a um chunk de referência

        Args:
            reference_chunk_id: ID do chunk de referência
            top_k: Número máximo de resultados
            threshold: Limite mínimo de similaridade

        Returns:
            Lista de chunks similares
        """
        try:
            # Busca o chunk de referência
            chunks = self.db_manager.get_chunks_with_embeddings(limit=1000)
            reference_chunk = None

            for chunk in chunks:
                if chunk['id'] == reference_chunk_id:
                    reference_chunk = chunk
                    break

            if not reference_chunk or not reference_chunk.get('content'):
                logger.error(f"Chunk de referência {reference_chunk_id} não encontrado")
                return []

            # Usa o conteúdo do chunk como query
            return self.search(
                reference_chunk['content'],
                top_k=top_k,
                threshold=threshold
            )

        except Exception as e:
            logger.error(f"Erro ao buscar chunks similares: {e}")
            return []

    def get_document_chunks(self, document_id: int, with_embeddings_only: bool = True) -> List[Dict]:
        """
        Retorna todos os chunks de um documento

        Args:
            document_id: ID do documento
            with_embeddings_only: Se True, retorna apenas chunks com embeddings

        Returns:
            Lista de chunks do documento
        """
        try:
            if with_embeddings_only:
                return self.db_manager.get_chunks_with_embeddings(document_id, limit=1000)
            else:
                # Implementar busca de todos os chunks se necessário
                return self.db_manager.get_chunks_with_embeddings(document_id, limit=1000)

        except Exception as e:
            logger.error(f"Erro ao buscar chunks do documento {document_id}: {e}")
            return []

    def health_check(self) -> Dict[str, Any]:
        """
        Verifica saúde do sistema de busca

        Returns:
            Dicionário com status dos componentes
        """
        health = {
            "database_connection": False,
            "embeddings_service": False,
            "total_chunks": 0,
            "chunks_with_embeddings": 0
        }

        try:
            # Testa conexão com banco
            health["database_connection"] = self.db_manager.test_connection()

            # Testa serviço de embeddings
            health["embeddings_service"] = self.embeddings_service.is_available()

            # Conta chunks com embeddings
            if health["database_connection"]:
                chunks = self.db_manager.get_chunks_with_embeddings(limit=10000)
                health["chunks_with_embeddings"] = len(chunks)

        except Exception as e:
            logger.error(f"Erro no health check: {e}")

        return health

    def close(self):
        """Fecha conexões e limpa recursos"""
        if self.db_manager:
            self.db_manager.close()