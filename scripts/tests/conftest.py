"""
Pytest configuration and fixtures for Enterprise Document Extraction API tests.
"""

import pytest
import asyncio
import tempfile
import os
from typing import AsyncGenerator, Generator
from fastapi.testclient import TestClient
from httpx import AsyncClient

# Test imports
import sys
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from api.main import app
from api.core.config import settings
from api.services.cache_service import CacheService
from api.services.metrics_service import MetricsService
from api.services.extractor_service import ExtractorService


@pytest.fixture(scope="session")
def event_loop():
    """Create an instance of the default event loop for the test session."""
    loop = asyncio.get_event_loop_policy().new_event_loop()
    yield loop
    loop.close()


@pytest.fixture
def client() -> Generator[TestClient, None, None]:
    """Create test client for synchronous tests."""
    with TestClient(app) as test_client:
        yield test_client


@pytest.fixture
async def async_client() -> AsyncGenerator[AsyncClient, None]:
    """Create async test client for asynchronous tests."""
    async with AsyncClient(app=app, base_url="http://test") as ac:
        yield ac


@pytest.fixture
def valid_api_key() -> str:
    """Return a valid API key for testing."""
    return "dev-key-123"


@pytest.fixture
def invalid_api_key() -> str:
    """Return an invalid API key for testing."""
    return "invalid-key"


@pytest.fixture
def auth_headers(valid_api_key: str) -> dict:
    """Return authentication headers with valid API key."""
    return {"Authorization": f"Bearer {valid_api_key}"}


@pytest.fixture
def invalid_auth_headers(invalid_api_key: str) -> dict:
    """Return authentication headers with invalid API key."""
    return {"Authorization": f"Bearer {invalid_api_key}"}


@pytest.fixture
def cache_service() -> CacheService:
    """Create cache service instance for testing."""
    return CacheService(redis_client=None)  # Use in-memory cache only


@pytest.fixture
def metrics_service() -> MetricsService:
    """Create metrics service instance for testing."""
    return MetricsService()


@pytest.fixture
def extractor_service() -> ExtractorService:
    """Create extractor service instance for testing."""
    return ExtractorService()


@pytest.fixture
def sample_pdf_content() -> bytes:
    """Create sample PDF content for testing."""
    # Simple PDF content (minimal valid PDF)
    pdf_content = b"""%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >>
>>
>>
endobj

4 0 obj
<<
/Length 44
>>
stream
BT
/F1 12 Tf
100 700 Td
(Hello World) Tj
ET
endstream
endobj

xref
0 5
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000294 00000 n
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
388
%%EOF"""
    return pdf_content


@pytest.fixture
def sample_text_content() -> bytes:
    """Create sample text content for testing."""
    return b"This is a sample text document for testing purposes.\nIt contains multiple lines.\nAnd some basic content."


@pytest.fixture
def sample_html_content() -> bytes:
    """Create sample HTML content for testing."""
    html_content = """<!DOCTYPE html>
<html>
<head>
    <title>Sample Document</title>
</head>
<body>
    <h1>Sample HTML Document</h1>
    <p>This is a paragraph with some text.</p>
    <ul>
        <li>List item 1</li>
        <li>List item 2</li>
    </ul>
    <table>
        <tr><th>Header 1</th><th>Header 2</th></tr>
        <tr><td>Cell 1</td><td>Cell 2</td></tr>
    </table>
</body>
</html>"""
    return html_content.encode('utf-8')


@pytest.fixture
def temp_file():
    """Create a temporary file for testing."""
    with tempfile.NamedTemporaryFile(delete=False) as temp:
        yield temp.name
    # Clean up
    try:
        os.unlink(temp.name)
    except FileNotFoundError:
        pass


@pytest.fixture
def mock_extraction_result():
    """Mock extraction result for testing."""
    return {
        "success": True,
        "file_type": "pdf",
        "extracted_text": "Sample extracted text from document",
        "quality_metrics": {
            "extraction_success_rate": 95.0,
            "total_pages": 1,
            "pages_processed": 1,
            "quality_rating": "good"
        },
        "error": None
    }


@pytest.fixture(scope="session")
def test_config():
    """Test configuration settings."""
    return {
        "api_keys": ["test-key-1", "test-key-2", "dev-key-123"],
        "rate_limit_requests": 10,  # Lower for testing
        "rate_limit_burst": 5,
        "max_file_size": 1024 * 1024,  # 1MB for testing
        "cache_ttl": 60,  # 1 minute for testing
    }


@pytest.fixture(autouse=True)
def setup_test_environment(monkeypatch):
    """Setup test environment variables."""
    monkeypatch.setenv("API_ENVIRONMENT", "testing")
    monkeypatch.setenv("API_DEBUG", "true")
    monkeypatch.setenv("API_LOG_LEVEL", "DEBUG")
    monkeypatch.setenv("API_REDIS_URL", "redis://localhost:6379/15")  # Use test database


# Custom markers
def pytest_configure(config):
    """Configure custom pytest markers."""
    config.addinivalue_line("markers", "slow: mark test as slow running")
    config.addinivalue_line("markers", "integration: mark test as integration test")
    config.addinivalue_line("markers", "unit: mark test as unit test")
    config.addinivalue_line("markers", "redis: mark test as requiring Redis")


# Skip Redis tests if Redis is not available
@pytest.fixture
def redis_available():
    """Check if Redis is available for testing."""
    try:
        import redis
        r = redis.Redis(host='localhost', port=6379, db=15)
        r.ping()
        return True
    except:
        return False


def pytest_runtest_setup(item):
    """Setup function run before each test."""
    if "redis" in item.keywords:
        pytest.importorskip("redis")
        # Check if Redis is available
        try:
            import redis
            r = redis.Redis(host='localhost', port=6379, db=15)
            r.ping()
        except:
            pytest.skip("Redis not available")