import psycopg2
import psycopg2.extras
from psycopg2.pool import SimpleConnectionPool
import logging
from contextlib import contextmanager
from typing import Dict, Any, Optional
from config import Config

logger = logging.getLogger(__name__)

class DatabaseManager:
    """Gerenciador de conexões PostgreSQL com pool"""

    def __init__(self, db_config: Optional[Dict[str, Any]] = None):
        """
        Inicializa o gerenciador de banco de dados

        Args:
            db_config: Configuração personalizada do banco, usa Config.DB_CONFIG se None
        """
        self.db_config = db_config or Config.DB_CONFIG
        self.pool = None
        self._create_connection_pool()

    def _create_connection_pool(self):
        """Cria pool de conexões"""
        try:
            connection_string = (
                f"host={self.db_config['host']} "
                f"dbname={self.db_config['database']} "
                f"user={self.db_config['user']} "
                f"password={self.db_config['password']} "
                f"port={self.db_config['port']}"
            )

            self.pool = SimpleConnectionPool(
                minconn=1,
                maxconn=5,
                dsn=connection_string
            )
            logger.info("Pool de conexões criado com sucesso")

        except Exception as e:
            logger.error(f"Erro ao criar pool de conexões: {e}")
            raise

    @contextmanager
    def get_connection(self):
        """Context manager para obter conexão do pool"""
        conn = None
        try:
            conn = self.pool.getconn()
            yield conn
        except Exception as e:
            if conn:
                conn.rollback()
            logger.error(f"Erro na conexão: {e}")
            raise
        finally:
            if conn:
                self.pool.putconn(conn)

    def test_connection(self) -> bool:
        """Testa se a conexão está funcionando"""
        try:
            with self.get_connection() as conn:
                with conn.cursor() as cursor:
                    cursor.execute("SELECT 1")
                    cursor.fetchone()
            return True
        except Exception as e:
            logger.error(f"Teste de conexão falhou: {e}")
            return False
    
    def is_pgsql(self) -> bool:
        """Verifica se está usando PostgreSQL"""
        try:
            with self.get_connection() as conn:
                return conn.info.server_version is not None
        except Exception:
            return False

    def get_chunks_with_embeddings(self, document_id: Optional[int] = None, limit: int = 5) -> list:
        """
        Busca chunks com embeddings

        Args:
            document_id: ID do documento específico (None para todos)
            limit: Limite de resultados

        Returns:
            Lista de chunks com embeddings
        """
        try:
            with self.get_connection() as conn:
                with conn.cursor(cursor_factory=psycopg2.extras.DictCursor) as cursor:
                    if document_id:
                        query = """
                            SELECT id, content, document_id, ord, embedding
                            FROM chunks
                            WHERE document_id = %s AND embedding IS NOT NULL
                            ORDER BY ord
                            LIMIT %s
                        """
                        cursor.execute(query, (document_id, limit))
                    else:
                        query = """
                            SELECT id, content, document_id, ord, embedding
                            FROM chunks
                            WHERE embedding IS NOT NULL
                            ORDER BY document_id, ord
                            LIMIT %s
                        """
                        cursor.execute(query, (limit,))

                    return cursor.fetchall()

        except Exception as e:
            logger.error(f"Erro ao buscar chunks: {e}")
            raise

    def execute_vector_search(self, query_embedding: list, document_id: Optional[int] = None,
                            top_k: int = 5, threshold: float = 0.3) -> list:
        """
        Executa busca vetorial usando similaridade cosseno

        Args:
            query_embedding: Embedding da query
            document_id: ID do documento específico (None para todos)
            top_k: Número de resultados
            threshold: Limite mínimo de similaridade

        Returns:
            Lista de chunks similares ordenados por similaridade
        """
        try:
            embedding_str = '[' + ','.join(map(str, query_embedding)) + ']'

            with self.get_connection() as conn:
                with conn.cursor(cursor_factory=psycopg2.extras.DictCursor) as cursor:
                    if document_id:
                        # Busca em documento específico
                        query = """
                            SELECT id, content, document_id, ord,
                                   1 - (embedding <=> %s::vector) AS similarity
                            FROM chunks
                            WHERE document_id = %s AND embedding IS NOT NULL
                            AND (1 - (embedding <=> %s::vector)) >= %s
                            ORDER BY similarity DESC
                            LIMIT %s
                        """
                        cursor.execute(query, (embedding_str, document_id, embedding_str, threshold, top_k))
                    else:
                        # Busca em todos os documentos
                        query = """
                            SELECT id, content, document_id, ord,
                                   1 - (embedding <=> %s::vector) AS similarity
                            FROM chunks
                            WHERE embedding IS NOT NULL
                            AND (1 - (embedding <=> %s::vector)) >= %s
                            ORDER BY similarity DESC
                            LIMIT %s
                        """
                        cursor.execute(query, (embedding_str, embedding_str, threshold, top_k))

                    results = cursor.fetchall()

                    # Converte DictRow para dict
                    results_dict = [dict(row) for row in results]

                    # Fallback: se não retornou nada via SQL, tenta busca em Python (cosine)
                    if not results_dict:
                        return self._fallback_cosine_search(query_embedding, document_id, top_k, threshold)

                    return results_dict

        except Exception as e:
            logger.error(f"Erro na busca vetorial: {e}")
            # Fallback para busca sem pgvector
            return self._fallback_cosine_search(query_embedding, document_id, top_k, threshold)

    def _fallback_cosine_search(self, query_embedding: list, document_id: Optional[int] = None,
                               top_k: int = 5, threshold: float = 0.3) -> list:
        """
        Busca usando similaridade cosseno calculada no Python (fallback)
        """
        import numpy as np
        from sklearn.metrics.pairwise import cosine_similarity

        try:
            # Busca todos os chunks com embeddings
            chunks = self.get_chunks_with_embeddings(document_id, limit=1000)

            if not chunks:
                return []

            # Prepara embeddings para comparação
            query_emb = np.array(query_embedding).reshape(1, -1)
            chunk_embeddings = []
            chunk_data = []

            for chunk in chunks:
                if chunk['embedding']:
                    # Converte embedding de string/bytes para array
                    if isinstance(chunk['embedding'], str):
                        import ast
                        emb = ast.literal_eval(chunk['embedding'])
                    else:
                        emb = chunk['embedding']

                    chunk_embeddings.append(emb)
                    chunk_data.append({
                        'id': chunk['id'],
                        'content': chunk['content'],
                        'document_id': chunk['document_id'],
                        'ord': chunk['ord']
                    })

            if not chunk_embeddings:
                return []

            # Calcula similaridades
            embeddings_matrix = np.array(chunk_embeddings)
            similarities = cosine_similarity(query_emb, embeddings_matrix)[0]

            # Combina dados com similaridades
            results = []
            for i, similarity in enumerate(similarities):
                if similarity >= threshold:
                    chunk_data[i]['similarity'] = float(similarity)
                    results.append(chunk_data[i])

            # Ordena por similaridade e limita
            results.sort(key=lambda x: x['similarity'], reverse=True)
            return results[:top_k]

        except Exception as e:
            logger.error(f"Erro no fallback de busca: {e}")
            return []

    def execute_query(self, sql: str, params: list = None) -> list:
        """
        Executa uma query SQL genérica e retorna resultados
        
        Args:
            sql: Query SQL
            params: Parâmetros da query
            
        Returns:
            Lista de dicionários com os resultados
        """
        try:
            with self.get_connection() as conn:
                with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cursor:
                    cursor.execute(sql, params or [])
                    results = cursor.fetchall()
                    return [dict(row) for row in results]
        except Exception as e:
            logger.error(f"Erro ao executar query: {e}")
            return []

    def close(self):
        """Fecha o pool de conexões"""
        if self.pool:
            self.pool.closeall()
            logger.info("Pool de conexões fechado")