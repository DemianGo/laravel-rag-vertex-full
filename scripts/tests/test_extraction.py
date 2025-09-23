"""
Unit tests for document extraction functionality.
"""

import pytest
import asyncio
from unittest.mock import Mock, patch, AsyncMock
from fastapi.testclient import TestClient
from httpx import AsyncClient
import tempfile
import os

from api.services.extractor_service import ExtractorService
from api.models.enums import FileType, ExtractionStatus
from api.utils.file_utils import FileValidator


class TestFileValidation:
    """Test file validation functionality."""

    def test_file_validator_init(self):
        """Test FileValidator initialization."""
        validator = FileValidator()
        assert validator.max_file_size > 0
        assert len(validator.allowed_mime_types) > 0
        assert len(validator.mime_to_filetype) > 0

    def test_validate_valid_pdf(self, sample_pdf_content):
        """Test validation of valid PDF file."""
        validator = FileValidator()
        is_valid, error, file_type = validator.validate_file(sample_pdf_content, "test.pdf")

        assert is_valid is True
        assert error is None
        assert file_type == FileType.PDF

    def test_validate_valid_text(self, sample_text_content):
        """Test validation of valid text file."""
        validator = FileValidator()
        is_valid, error, file_type = validator.validate_file(sample_text_content, "test.txt")

        assert is_valid is True
        assert error is None
        assert file_type == FileType.TXT

    def test_validate_empty_file(self):
        """Test validation of empty file."""
        validator = FileValidator()
        is_valid, error, file_type = validator.validate_file(b"", "test.txt")

        assert is_valid is False
        assert "empty" in error.lower()
        assert file_type is None

    def test_validate_oversized_file(self):
        """Test validation of oversized file."""
        validator = FileValidator()
        # Create content larger than max size
        large_content = b"x" * (validator.max_file_size + 1)
        is_valid, error, file_type = validator.validate_file(large_content, "test.txt")

        assert is_valid is False
        assert "exceeds maximum" in error
        assert file_type is None

    def test_detect_file_type_by_content(self, sample_html_content):
        """Test file type detection by content."""
        validator = FileValidator()
        mime_type, file_type = validator.detect_file_type(sample_html_content, "test.html")

        assert file_type == FileType.HTML
        assert mime_type is not None

    def test_supported_extensions(self):
        """Test supported extension checking."""
        validator = FileValidator()

        assert validator.is_supported_extension("test.pdf") is True
        assert validator.is_supported_extension("test.docx") is True
        assert validator.is_supported_extension("test.xyz") is False

    def test_get_supported_formats(self):
        """Test getting supported formats list."""
        validator = FileValidator()
        formats = validator.get_supported_formats()

        assert len(formats) > 0
        assert all("file_type" in fmt for fmt in formats)
        assert all("mime_type" in fmt for fmt in formats)
        assert all("extensions" in fmt for fmt in formats)


class TestExtractionService:
    """Test extraction service functionality."""

    @pytest.fixture
    def service(self):
        """Create extraction service instance."""
        return ExtractorService()

    def test_service_initialization(self, service):
        """Test service initialization."""
        assert service.active_jobs == {}
        assert service.max_concurrent_jobs > 0

    def test_generate_job_id(self, service):
        """Test job ID generation."""
        job_id1 = service._generate_job_id()
        job_id2 = service._generate_job_id()

        assert job_id1.startswith("job_")
        assert job_id2.startswith("job_")
        assert job_id1 != job_id2  # Should be unique

    @patch('api.services.extractor_service.extract_document')
    @pytest.mark.asyncio
    async def test_extract_file_success(self, mock_extract, service, sample_text_content, mock_extraction_result):
        """Test successful file extraction."""
        mock_extract.return_value = mock_extraction_result

        result = await service.extract_file(
            file_content=sample_text_content,
            filename="test.txt",
            api_key="test-key"
        )

        assert result.file_type == FileType.PDF  # From mock
        assert result.extracted_text == "Sample extracted text from document"
        assert result.quality_metrics.quality_rating == "good"

    @patch('api.services.extractor_service.extract_document')
    @pytest.mark.asyncio
    async def test_extract_file_failure(self, mock_extract, service, sample_text_content):
        """Test file extraction failure."""
        mock_extract.return_value = {
            "success": False,
            "error": "Extraction failed"
        }

        with pytest.raises(Exception, match="Extraction failed"):
            await service.extract_file(
                file_content=sample_text_content,
                filename="test.txt",
                api_key="test-key"
            )

    def test_get_job_status_existing(self, service):
        """Test getting status of existing job."""
        # Create a mock job
        from api.services.extractor_service import ExtractionJob
        from api.models.enums import JobType

        job_id = "test-job-123"
        job = ExtractionJob(job_id, JobType.SINGLE_FILE, "test-key")
        service.active_jobs[job_id] = job

        retrieved_job = service.get_job_status(job_id)

        assert retrieved_job is not None
        assert retrieved_job.job_id == job_id
        assert retrieved_job.api_key == "test-key"

    def test_get_job_status_nonexistent(self, service):
        """Test getting status of non-existent job."""
        result = service.get_job_status("nonexistent-job")
        assert result is None

    @pytest.mark.asyncio
    async def test_cleanup_old_jobs(self, service):
        """Test cleanup of old jobs."""
        from api.services.extractor_service import ExtractionJob
        from api.models.enums import JobType, ExtractionStatus
        from datetime import datetime, timedelta

        # Create old completed job
        old_job = ExtractionJob("old-job", JobType.SINGLE_FILE, "test-key")
        old_job.status = ExtractionStatus.COMPLETED
        old_job.updated_at = datetime.utcnow() - timedelta(hours=25)  # 25 hours old

        # Create recent job
        recent_job = ExtractionJob("recent-job", JobType.SINGLE_FILE, "test-key")
        recent_job.status = ExtractionStatus.COMPLETED

        service.active_jobs = {
            "old-job": old_job,
            "recent-job": recent_job
        }

        await service.cleanup_old_jobs(max_age_hours=24)

        # Old job should be removed, recent job should remain
        assert "old-job" not in service.active_jobs
        assert "recent-job" in service.active_jobs


class TestExtractionAPI:
    """Test extraction API endpoints."""

    def test_extract_file_no_auth(self, client):
        """Test file extraction without authentication."""
        response = client.post(
            "/v1/extract/file",
            files={"file": ("test.txt", b"test content", "text/plain")}
        )
        assert response.status_code == 401

    def test_extract_file_invalid_auth(self, client, invalid_auth_headers):
        """Test file extraction with invalid authentication."""
        response = client.post(
            "/v1/extract/file",
            headers=invalid_auth_headers,
            files={"file": ("test.txt", b"test content", "text/plain")}
        )
        assert response.status_code == 401

    @patch('api.services.extractor_service.ExtractorService.extract_file')
    def test_extract_file_success(self, mock_extract, client, auth_headers, sample_text_content):
        """Test successful file extraction via API."""
        # Mock the extraction result
        from api.models.responses import ExtractionResult, QualityMetrics
        from api.models.enums import FileType

        mock_result = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Sample extracted text",
            quality_metrics=QualityMetrics(
                extraction_success_rate=95.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=20,
                word_count=3
            ),
            processing_time=0.5,
            file_size=len(sample_text_content),
            filename="test.txt"
        )

        mock_extract.return_value = mock_result

        response = client.post(
            "/v1/extract/file",
            headers=auth_headers,
            files={"file": ("test.txt", sample_text_content, "text/plain")}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["result"]["file_type"] == "txt"
        assert data["result"]["extracted_text"] == "Sample extracted text"

    def test_extract_file_invalid_format(self, client, auth_headers):
        """Test extraction with invalid file format."""
        response = client.post(
            "/v1/extract/file",
            headers=auth_headers,
            files={"file": ("test.xyz", b"invalid content", "application/octet-stream")}
        )

        assert response.status_code == 400
        data = response.json()
        assert data["success"] is False
        assert "INVALID_FILE_TYPE" in data["error"]["code"]

    def test_extract_file_empty(self, client, auth_headers):
        """Test extraction with empty file."""
        response = client.post(
            "/v1/extract/file",
            headers=auth_headers,
            files={"file": ("test.txt", b"", "text/plain")}
        )

        assert response.status_code == 400
        data = response.json()
        assert data["success"] is False

    def test_extract_url_no_auth(self, client):
        """Test URL extraction without authentication."""
        response = client.post(
            "/v1/extract/url",
            json={"url": "https://example.com/document.pdf"}
        )
        assert response.status_code == 401

    def test_extract_url_invalid_url(self, client, auth_headers):
        """Test URL extraction with invalid URL."""
        response = client.post(
            "/v1/extract/url",
            headers=auth_headers,
            json={"url": "not-a-url"}
        )
        assert response.status_code == 422  # Validation error

    def test_extract_url_http_not_allowed(self, client, auth_headers):
        """Test URL extraction with HTTP URL (should require HTTPS)."""
        response = client.post(
            "/v1/extract/url",
            headers=auth_headers,
            json={"url": "http://example.com/document.pdf"}
        )
        assert response.status_code == 400

    def test_get_job_status_no_auth(self, client):
        """Test job status without authentication."""
        response = client.get("/v1/extract/status/test-job")
        assert response.status_code == 401

    def test_get_job_status_not_found(self, client, auth_headers):
        """Test job status for non-existent job."""
        response = client.get(
            "/v1/extract/status/nonexistent-job",
            headers=auth_headers
        )
        assert response.status_code == 404

    def test_formats_endpoint(self, client, auth_headers):
        """Test formats endpoint."""
        response = client.get("/v1/admin/formats", headers=auth_headers)
        assert response.status_code == 200

        data = response.json()
        assert data["success"] is True
        assert "formats" in data
        assert len(data["formats"]) > 0


@pytest.mark.integration
class TestIntegrationExtraction:
    """Integration tests for extraction functionality."""

    def test_real_text_extraction(self, temp_file):
        """Test extraction with real text file."""
        # Create a real text file
        content = "This is a test document.\nIt has multiple lines.\nAnd some content to extract."

        with open(temp_file, 'w') as f:
            f.write(content)

        # Test the actual extractor
        from document_extraction.main_extractor import extract_document

        result = extract_document(temp_file)

        assert result["success"] is True
        assert result["file_type"] == "txt"
        assert content in result["extracted_text"]

    @pytest.mark.slow
    def test_pdf_extraction_performance(self, sample_pdf_content, temp_file):
        """Test PDF extraction performance."""
        import time

        # Write PDF content to temp file
        with open(temp_file, 'wb') as f:
            f.write(sample_pdf_content)

        # Add .pdf extension
        pdf_file = temp_file + ".pdf"
        os.rename(temp_file, pdf_file)

        try:
            start_time = time.time()

            from document_extraction.main_extractor import extract_document
            result = extract_document(pdf_file)

            end_time = time.time()
            extraction_time = end_time - start_time

            # Performance assertions
            assert extraction_time < 5.0  # Should complete in under 5 seconds
            assert result["success"] is True

        finally:
            # Clean up
            try:
                os.unlink(pdf_file)
            except:
                pass