"""
Enterprise configuration management with Pydantic Settings.
"""

import os
from typing import Optional, List
from pydantic_settings import BaseSettings
from pydantic import validator
from functools import lru_cache


class Settings(BaseSettings):
    """Application settings with environment-based configuration."""

    # Application
    app_name: str = "Enterprise Document Extraction API"
    app_version: str = "1.0.0"
    environment: str = "development"
    debug: bool = False
    api_port: int = 8002

    # API Configuration
    api_prefix: str = "/v1"
    docs_url: str = "/docs"
    redoc_url: str = "/redoc"
    openapi_url: str = "/openapi.json"

    # Security
    api_keys: List[str] = ["dev-key-123", "prod-key-456", "test-key-789"]
    secret_key: str = "your-super-secret-key-change-this-in-production"

    # Rate Limiting
    rate_limit_requests: int = 100  # requests per minute per API key
    rate_limit_burst: int = 20      # burst limit

    # File Processing
    max_file_size: int = 500 * 1024 * 1024  # 500MB (5000 pages support)
    allowed_file_types: List[str] = [
        "application/pdf",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "text/plain",
        "text/csv",
        "text/html",
        "application/xml",
        "text/xml",
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/bmp",
        "image/tiff",
        "image/webp"
    ]

    # Redis Configuration
    redis_url: str = "redis://localhost:6379/0"
    redis_password: Optional[str] = None
    redis_ssl: bool = False
    cache_ttl: int = 3600  # 1 hour

    # Database (future expansion)
    database_url: Optional[str] = None

    # Logging
    log_level: str = "INFO"
    log_format: str = "json"  # json or text

    # Performance
    worker_processes: int = 1
    max_concurrent_requests: int = 100
    request_timeout: int = 1800  # 30 minutes (for 5000 pages)

    # Feature Flags
    enable_batch_processing: bool = True
    enable_url_extraction: bool = True
    enable_webhooks: bool = False
    enable_metrics: bool = True

    # Monitoring
    metrics_path: str = "/metrics"
    health_check_path: str = "/health"

    # Notification/Webhook settings
    webhook_timeout: int = 30
    webhook_retry_attempts: int = 3

    @validator("environment")
    def validate_environment(cls, v):
        allowed = ["development", "staging", "production"]
        if v not in allowed:
            raise ValueError(f"Environment must be one of {allowed}")
        return v

    @validator("log_level")
    def validate_log_level(cls, v):
        allowed = ["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"]
        if v not in allowed:
            raise ValueError(f"Log level must be one of {allowed}")
        return v

    @validator("api_keys")
    def validate_api_keys(cls, v):
        if not v or len(v) == 0:
            raise ValueError("At least one API key must be configured")
        for key in v:
            if len(key) < 10:
                raise ValueError("API keys must be at least 10 characters long")
        return v

    class Config:
        env_file = ".env"
        env_prefix = "API_"


@lru_cache()
def get_settings() -> Settings:
    """Get cached settings instance."""
    return Settings()


# Global settings instance
settings = get_settings()