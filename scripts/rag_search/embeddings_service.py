import numpy as np
import logging
from typing import List, Union, Optional
from config import Config

logger = logging.getLogger(__name__)

class EmbeddingsService:
    """Serviço para geração de embeddings usando sentence-transformers com fallback para OpenAI"""

    def __init__(self, config=None):
        self.config = config or Config
        self.model = None
        self.model_name = "sentence-transformers/all-mpnet-base-v2"
        self.dimension = 768
        self._load_model()

    def _load_model(self):
        """Carrega o modelo sentence-transformers"""
        try:
            from sentence_transformers import SentenceTransformer
            self.model = SentenceTransformer(self.model_name)
            logger.info(f"Modelo {self.model_name} carregado com sucesso")

        except ImportError:
            logger.warning("sentence-transformers não disponível, usando fallback OpenAI")
            self.model = None

        except Exception as e:
            logger.error(f"Erro ao carregar modelo {self.model_name}: {e}")
            self.model = None
        
        # Força o modelo de embeddings para compatibilidade com 768 dimensões (mpnet)
        self.config.embedding_model = "sentence-transformers/all-mpnet-base-v2"

    def encode(self, text: str) -> np.ndarray:
        """
        Gera embedding para um texto

        Args:
            text: Texto para gerar embedding

        Returns:
            Array numpy com o embedding
        """
        if not text or not text.strip():
            return np.zeros(self.dimension)

        # Tenta usar sentence-transformers
        if self.model is not None:
            try:
                embedding = self.model.encode(text, convert_to_numpy=True)
                return embedding.astype(np.float32)

            except Exception as e:
                logger.error(f"Erro ao gerar embedding com sentence-transformers: {e}")

        # Fallback para OpenAI
        return self._encode_with_openai(text)

    def encode_batch(self, texts: List[str]) -> List[np.ndarray]:
        """
        Gera embeddings para múltiplos textos

        Args:
            texts: Lista de textos

        Returns:
            Lista de arrays numpy com os embeddings
        """
        if not texts:
            return []

        # Remove textos vazios
        valid_texts = [text.strip() if text else "" for text in texts]

        # Tenta usar sentence-transformers
        if self.model is not None:
            try:
                embeddings = self.model.encode(valid_texts, convert_to_numpy=True)
                return [emb.astype(np.float32) for emb in embeddings]

            except Exception as e:
                logger.error(f"Erro ao gerar embeddings batch com sentence-transformers: {e}")

        # Fallback para processar individualmente
        return [self.encode(text) for text in valid_texts]

    def _encode_with_openai(self, text: str) -> np.ndarray:
        """
        Fallback usando OpenAI embeddings

        Args:
            text: Texto para gerar embedding

        Returns:
            Array numpy com o embedding
        """
        try:
            import openai
            openai.api_key = self.config.OPENAI_API_KEY

            if not self.config.OPENAI_API_KEY:
                logger.warning("OPENAI_API_KEY não configurado, retornando embedding zerado")
                return np.zeros(self.dimension)

            response = openai.embeddings.create(
                input=text,
                model="text-embedding-ada-002"
            )

            embedding = np.array(response.data[0].embedding, dtype=np.float32)

            # Ajusta dimensão se necessário (OpenAI usa 1536, nosso modelo padrão usa 384)
            if len(embedding) != self.dimension:
                # Trunca ou pad conforme necessário
                if len(embedding) > self.dimension:
                    embedding = embedding[:self.dimension]
                else:
                    padding = np.zeros(self.dimension - len(embedding))
                    embedding = np.concatenate([embedding, padding])

            return embedding

        except ImportError:
            logger.error("Biblioteca openai não disponível")
            return np.zeros(self.dimension)

        except Exception as e:
            logger.error(f"Erro ao usar OpenAI embeddings: {e}")
            return np.zeros(self.dimension)

    def get_dimension(self) -> int:
        """Retorna a dimensão dos embeddings"""
        return self.dimension

    def is_available(self) -> bool:
        """Verifica se o serviço está disponível"""
        return (self.model is not None) or bool(self.config.OPENAI_API_KEY)

    def get_model_info(self) -> dict:
        """Retorna informações sobre o modelo em uso"""
        return {
            "model_name": self.model_name,
            "dimension": self.dimension,
            "sentence_transformers_available": self.model is not None,
            "openai_available": bool(self.config.OPENAI_API_KEY)
        }