import os
from pathlib import Path
from dotenv import load_dotenv

# Carregar .env do diretório raiz do projeto (independente do CWD)
project_root = Path(__file__).parent.parent.parent
env_path = project_root / '.env'
load_dotenv(dotenv_path=env_path)

class Config:
    """Configurações do sistema RAG"""
    
    def __init__(self, db_config=None):
        """Inicializa configurações com possível override de DB"""
        if db_config:
            self.DB_CONFIG = self.get_db_config_from_json(db_config)
        else:
            self.DB_CONFIG = self.__class__.DB_CONFIG.copy()

    # Configurações de banco de dados (classe)
    DB_CONFIG = {
        "host": os.getenv("DB_HOST", "localhost"),
        "database": os.getenv("DB_DATABASE", "laravel"),
        "user": os.getenv("DB_USERNAME", "postgres"),
        "password": os.getenv("DB_PASSWORD", ""),
        "port": os.getenv("DB_PORT", "5432")
    }

    # Configurações de embeddings
    EMBEDDINGS_MODEL = "sentence-transformers/all-mpnet-base-v2"
    EMBEDDINGS_DIMENSION = 768

    # Configurações de busca
    DEFAULT_TOP_K = 5
    SIMILARITY_THRESHOLD = 0.3

    # Configurações de LLM
    GEMINI_API_KEY = os.getenv("GOOGLE_GENAI_API_KEY", os.getenv("GEMINI_API_KEY", ""))
    OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
    DEFAULT_LLM_PROVIDER = "openai"  # gemini, openai, local - TEMPORARIAMENTE OPENAI P/ GROUNDING

    # Configurações de timeout
    DATABASE_TIMEOUT = 30
    LLM_TIMEOUT = 60

    # Configurações de logging
    LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO")

    @classmethod 
    def get_db_config_from_json(cls, json_config=None):
        """Sobrescreve configuração de DB a partir de JSON do Laravel"""
        if json_config:
            config = cls.DB_CONFIG.copy()
            config.update(json_config)
            return config
        return cls.DB_CONFIG
    
    def get_db_config_from_json(self, json_config=None):
        """Método de instância para sobrescrever configuração de DB"""
        if json_config:
            config = self.__class__.DB_CONFIG.copy()
            config.update(json_config)
            return config
        return self.DB_CONFIG