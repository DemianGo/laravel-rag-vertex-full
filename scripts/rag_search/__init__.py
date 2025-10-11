"""
Sistema RAG de Busca Vetorial
============================

Sistema completo de busca RAG (Retrieval-Augmented Generation) que integra:
- Geração de embeddings com sentence-transformers ou OpenAI
- Busca vetorial no PostgreSQL usando pgvector
- Geração de respostas com LLMs (Gemini/OpenAI)
- CLI para integração com Laravel

Componentes principais:
- config: Configurações do sistema
- database: Gerenciamento de conexões PostgreSQL
- embeddings_service: Geração de embeddings
- vector_search: Motor de busca vetorial
- llm_service: Interface com LLMs
- rag_search: Script CLI principal

Uso básico:
    python rag_search.py --query "Sua pergunta" --document-id 1

Para integração com Laravel:
    $result = shell_exec("python3 scripts/rag_search/rag_search.py --query " . escapeshellarg($query));
    $data = json_decode($result, true);
"""

from .config import Config
from .database import DatabaseManager
from .embeddings_service import EmbeddingsService
from .vector_search import VectorSearchEngine
from .llm_service import LLMService

__version__ = "1.0.0"
__author__ = "Sistema RAG Laravel"

__all__ = [
    "Config",
    "DatabaseManager",
    "EmbeddingsService",
    "VectorSearchEngine",
    "LLMService"
]