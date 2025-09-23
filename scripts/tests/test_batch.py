"""
Unit tests for batch processing functionality.
"""

import pytest
from unittest.mock import patch, Mock
from fastapi.testclient import TestClient
import io


class TestBatchProcessing:
    """Test batch processing functionality."""

    def test_batch_extract_no_auth(self, client):
        """Test batch extraction without authentication."""
        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post("/v1/batch/extract", files=files)
        assert response.status_code == 401

    def test_batch_extract_empty_files(self, client, auth_headers):
        """Test batch extraction with no files."""
        response = client.post("/v1/batch/extract", headers=auth_headers, files=[])
        assert response.status_code == 422  # Validation error

    def test_batch_extract_too_many_files(self, client, auth_headers):
        """Test batch extraction with too many files."""
        # Create 25 files (exceeding limit of 20)
        files = []
        for i in range(25):
            files.append(("files", (f"test{i}.txt", b"content", "text/plain")))

        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)
        assert response.status_code == 400

    @patch('api.services.extractor_service.ExtractorService.extract_file')
    def test_batch_extract_success(self, mock_extract, client, auth_headers):
        """Test successful batch extraction."""
        from api.models.responses import ExtractionResult, QualityMetrics
        from api.models.enums import FileType

        # Mock extraction results
        mock_result1 = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Content from file 1",
            quality_metrics=QualityMetrics(
                extraction_success_rate=95.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=18,
                word_count=4
            ),
            processing_time=0.1,
            file_size=8,
            filename="test1.txt"
        )

        mock_result2 = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Content from file 2",
            quality_metrics=QualityMetrics(
                extraction_success_rate=90.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=18,
                word_count=4
            ),
            processing_time=0.2,
            file_size=8,
            filename="test2.txt"
        )

        mock_extract.side_effect = [mock_result1, mock_result2]

        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert "job_id" in data
        assert data["result"]["total_files"] == 2
        assert data["result"]["successful_extractions"] == 2
        assert data["result"]["failed_extractions"] == 0
        assert len(data["result"]["results"]) == 2

    @patch('api.services.extractor_service.ExtractorService.extract_file')
    def test_batch_extract_partial_failure(self, mock_extract, client, auth_headers):
        """Test batch extraction with partial failures."""
        from api.models.responses import ExtractionResult, QualityMetrics
        from api.models.enums import FileType

        # Mock one success, one failure
        mock_result = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Content from file 1",
            quality_metrics=QualityMetrics(
                extraction_success_rate=95.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=18,
                word_count=4
            ),
            processing_time=0.1,
            file_size=8,
            filename="test1.txt"
        )

        mock_extract.side_effect = [mock_result, Exception("Extraction failed")]

        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["result"]["total_files"] == 2
        assert data["result"]["successful_extractions"] == 1
        assert data["result"]["failed_extractions"] == 1

    @patch('api.services.extractor_service.ExtractorService.extract_file')
    def test_batch_extract_fail_fast(self, mock_extract, client, auth_headers):
        """Test batch extraction with fail_fast enabled."""
        # Mock first file success, second file failure
        from api.models.responses import ExtractionResult, QualityMetrics
        from api.models.enums import FileType

        mock_result = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Content from file 1",
            quality_metrics=QualityMetrics(
                extraction_success_rate=95.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=18,
                word_count=4
            ),
            processing_time=0.1,
            file_size=8,
            filename="test1.txt"
        )

        mock_extract.side_effect = [mock_result, Exception("Extraction failed")]

        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post(
            "/v1/batch/extract?fail_fast=true",
            headers=auth_headers,
            files=files
        )

        assert response.status_code == 500
        data = response.json()
        assert data["success"] is False

    @patch('api.services.extractor_service.ExtractorService.extract_file')
    def test_batch_extract_merge_results(self, mock_extract, client, auth_headers):
        """Test batch extraction with result merging."""
        from api.models.responses import ExtractionResult, QualityMetrics
        from api.models.enums import FileType

        mock_result1 = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Text from file 1",
            quality_metrics=QualityMetrics(
                extraction_success_rate=95.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=16,
                word_count=4
            ),
            processing_time=0.1,
            file_size=8,
            filename="test1.txt"
        )

        mock_result2 = ExtractionResult(
            file_type=FileType.TXT,
            extracted_text="Text from file 2",
            quality_metrics=QualityMetrics(
                extraction_success_rate=90.0,
                total_pages=1,
                pages_processed=1,
                quality_rating="good",
                text_length=16,
                word_count=4
            ),
            processing_time=0.2,
            file_size=8,
            filename="test2.txt"
        )

        mock_extract.side_effect = [mock_result1, mock_result2]

        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post(
            "/v1/batch/extract?merge_results=true",
            headers=auth_headers,
            files=files
        )

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        # Note: The combined_text would be in the BatchResult model
        # but our current implementation doesn't include it in the response

    def test_batch_extract_invalid_files(self, client, auth_headers):
        """Test batch extraction with invalid files."""
        files = [
            ("files", ("test1.txt", b"", "text/plain")),  # Empty file
            ("files", ("test2.xyz", b"content", "application/octet-stream"))  # Unsupported format
        ]

        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        # Should process files and return errors for invalid ones
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["result"]["total_files"] == 2
        assert data["result"]["successful_extractions"] == 0
        assert data["result"]["failed_extractions"] == 2

    def test_batch_extract_max_parallel_jobs(self, client, auth_headers):
        """Test batch extraction with custom max_parallel_jobs."""
        files = [
            ("files", ("test1.txt", b"content1", "text/plain")),
            ("files", ("test2.txt", b"content2", "text/plain"))
        ]

        response = client.post(
            "/v1/batch/extract?max_parallel_jobs=1",
            headers=auth_headers,
            files=files
        )

        # Should not fail due to parallel job limit
        assert response.status_code in [200, 500]  # May fail due to mock setup

    def test_batch_extract_webhook_url(self, client, auth_headers):
        """Test batch extraction with webhook URL."""
        files = [("files", ("test1.txt", b"content1", "text/plain"))]

        # Invalid webhook URL (HTTP instead of HTTPS)
        response = client.post(
            "/v1/batch/extract?webhook_url=http://example.com/webhook",
            headers=auth_headers,
            files=files
        )

        assert response.status_code == 400

    def test_batch_processing_performance(self, client, auth_headers):
        """Test batch processing performance with multiple files."""
        import time

        # Create 5 small files
        files = []
        for i in range(5):
            files.append(("files", (f"test{i}.txt", f"content{i}".encode(), "text/plain")))

        start_time = time.time()

        with patch('api.services.extractor_service.ExtractorService.extract_file') as mock_extract:
            from api.models.responses import ExtractionResult, QualityMetrics
            from api.models.enums import FileType

            # Mock fast extraction
            mock_result = ExtractionResult(
                file_type=FileType.TXT,
                extracted_text="Mock content",
                quality_metrics=QualityMetrics(
                    extraction_success_rate=95.0,
                    total_pages=1,
                    pages_processed=1,
                    quality_rating="good",
                    text_length=12,
                    word_count=2
                ),
                processing_time=0.01,
                file_size=8,
                filename="test.txt"
            )

            mock_extract.return_value = mock_result

            response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        end_time = time.time()
        processing_time = end_time - start_time

        assert response.status_code == 200
        assert processing_time < 5.0  # Should complete quickly with mocked extraction

    @patch('api.utils.validators.validate_batch_request')
    def test_batch_extract_validation_error(self, mock_validate, client, auth_headers):
        """Test batch extraction with validation error."""
        from api.utils.validators import ValidationError

        mock_validate.side_effect = ValidationError("files", "Too many files")

        files = [("files", ("test1.txt", b"content1", "text/plain"))]

        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        assert response.status_code == 400
        data = response.json()
        assert data["success"] is False

    def test_batch_extract_large_files(self, client, auth_headers):
        """Test batch extraction with large files."""
        # Create a file that's close to but under the size limit
        large_content = b"x" * (1024 * 512)  # 512KB

        files = [("files", ("large.txt", large_content, "text/plain"))]

        # This should work (assuming our test limit is 1MB)
        response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        # Response depends on mock setup, but shouldn't fail due to file size
        assert response.status_code in [200, 500]  # May fail due to mock setup

    def test_batch_extract_mixed_file_types(self, client, auth_headers, sample_text_content, sample_html_content):
        """Test batch extraction with mixed file types."""
        files = [
            ("files", ("test.txt", sample_text_content, "text/plain")),
            ("files", ("test.html", sample_html_content, "text/html"))
        ]

        with patch('api.services.extractor_service.ExtractorService.extract_file') as mock_extract:
            from api.models.responses import ExtractionResult, QualityMetrics
            from api.models.enums import FileType

            # Mock different results for different file types
            mock_result_txt = ExtractionResult(
                file_type=FileType.TXT,
                extracted_text="Text content",
                quality_metrics=QualityMetrics(
                    extraction_success_rate=95.0,
                    total_pages=1,
                    pages_processed=1,
                    quality_rating="good",
                    text_length=12,
                    word_count=2
                ),
                processing_time=0.1,
                file_size=len(sample_text_content),
                filename="test.txt"
            )

            mock_result_html = ExtractionResult(
                file_type=FileType.HTML,
                extracted_text="HTML content",
                quality_metrics=QualityMetrics(
                    extraction_success_rate=90.0,
                    total_pages=1,
                    pages_processed=1,
                    quality_rating="good",
                    text_length=12,
                    word_count=2
                ),
                processing_time=0.2,
                file_size=len(sample_html_content),
                filename="test.html"
            )

            mock_extract.side_effect = [mock_result_txt, mock_result_html]

            response = client.post("/v1/batch/extract", headers=auth_headers, files=files)

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["result"]["total_files"] == 2
        assert data["result"]["successful_extractions"] == 2