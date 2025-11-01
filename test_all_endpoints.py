#!/usr/bin/env python3
"""
Test script for all new FastAPI endpoints
Tests with real data, including large files
"""

import requests
import json
import time
import os
from pathlib import Path
from typing import Dict, Any, List

BASE_URL = "http://localhost:8002"
TEST_TIMEOUT = 60  # 60 seconds max per test

# Colors for output
GREEN = "\033[92m"
RED = "\033[91m"
YELLOW = "\033[93m"
BLUE = "\033[94m"
RESET = "\033[0m"

class EndpointTester:
    def __init__(self):
        self.results = []
        self.api_key = None
        
    def print_status(self, message: str, status: str = "info"):
        """Print colored status message"""
        colors = {
            "success": GREEN,
            "error": RED,
            "warning": YELLOW,
            "info": BLUE
        }
        color = colors.get(status, RESET)
        print(f"{color}[{status.upper()}]{RESET} {message}")
    
    def test_auth(self) -> bool:
        """Test authentication and get API key"""
        self.print_status("Testing authentication...", "info")
        
        try:
            # Register test user
            response = requests.post(
                f"{BASE_URL}/auth/register",
                json={
                    "email": f"test_{int(time.time())}@example.com",
                    "password": "TestPassword123!",
                    "name": "Test User"
                },
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                self.api_key = data.get("api_key")
                self.print_status(f"✅ Auth OK - API Key: {self.api_key[:20]}...", "success")
                return True
            else:
                self.print_status(f"❌ Auth failed: {response.status_code}", "error")
                return False
                
        except Exception as e:
            self.print_status(f"❌ Auth error: {str(e)}", "error")
            return False
    
    def test_endpoint(self, name: str, method: str, url: str, 
                     data: Any = None, files: Dict = None, 
                     expected_status: int = 200, timeout: int = TEST_TIMEOUT) -> Dict[str, Any]:
        """Test a single endpoint"""
        start_time = time.time()
        
        try:
            headers = {"X-API-Key": self.api_key} if self.api_key else {}
            
            if method == "GET":
                response = requests.get(url, headers=headers, timeout=timeout)
            elif method == "POST":
                if files:
                    response = requests.post(url, headers=headers, files=files, data=data, timeout=timeout)
                else:
                    headers["Content-Type"] = "application/json"
                    response = requests.post(url, headers=headers, json=data, timeout=timeout)
            elif method == "DELETE":
                response = requests.delete(url, headers=headers, timeout=timeout)
            else:
                raise ValueError(f"Unknown method: {method}")
            
            duration = time.time() - start_time
            success = response.status_code == expected_status
            
            result = {
                "name": name,
                "method": method,
                "url": url,
                "status_code": response.status_code,
                "expected_status": expected_status,
                "success": success,
                "duration": round(duration, 2),
                "response_size": len(response.content) if response.content else 0
            }
            
            try:
                result["response_data"] = response.json()
            except:
                result["response_data"] = response.text[:200] if response.text else None
            
            if success:
                self.print_status(f"✅ {name} - {duration:.2f}s", "success")
            else:
                self.print_status(f"❌ {name} - Status {response.status_code} (expected {expected_status})", "error")
            
            return result
            
        except requests.exceptions.Timeout:
            duration = time.time() - start_time
            self.print_status(f"⏱️ {name} - TIMEOUT after {timeout}s", "warning")
            return {
                "name": name,
                "method": method,
                "url": url,
                "success": False,
                "error": "Timeout",
                "duration": timeout
            }
        except Exception as e:
            duration = time.time() - start_time
            self.print_status(f"❌ {name} - Error: {str(e)}", "error")
            return {
                "name": name,
                "method": method,
                "url": url,
                "success": False,
                "error": str(e),
                "duration": round(duration, 2)
            }
    
    def create_test_file(self, content: str, filename: str) -> Path:
        """Create a test file"""
        test_dir = Path("/tmp/rag_test")
        test_dir.mkdir(exist_ok=True)
        file_path = test_dir / filename
        file_path.write_text(content)
        return file_path
    
    def run_all_tests(self):
        """Run all endpoint tests"""
        print(f"\n{BLUE}{'='*60}{RESET}")
        print(f"{BLUE}FASTAPI ENDPOINTS TEST SUITE{RESET}")
        print(f"{BLUE}{'='*60}{RESET}\n")
        
        # Step 1: Auth
        if not self.test_auth():
            self.print_status("Cannot continue without API key", "error")
            return
        
        # Step 2: Upload a document first (needed for other tests)
        self.print_status("\n📄 Step 1: Upload test document...", "info")
        test_file = self.create_test_file(
            "Este é um documento de teste para validação do sistema RAG.\n" * 100,
            "test_document.txt"
        )
        
        upload_result = self.test_endpoint(
            "Upload Document",
            "POST",
            f"{BASE_URL}/api/rag/ingest",
            files={"file": ("test_document.txt", open(test_file, "rb"), "text/plain")},
            data={"title": "Test Document"},
            timeout=120
        )
        
        doc_id = None
        if upload_result.get("success") and upload_result.get("response_data"):
            doc_id = upload_result["response_data"].get("document_id")
        
        # Step 3: Test all endpoints
        self.print_status("\n🧪 Step 2: Testing all endpoints...", "info")
        
        tests = []
        
        # RAG Endpoints
        if doc_id:
            tests.extend([
                ("Get Document Chunks", "GET", f"{BASE_URL}/api/rag/docs/{doc_id}/chunks"),
                ("Generate Embeddings", "POST", f"{BASE_URL}/api/rag/embeddings/generate", 
                 {"document_id": doc_id}),
            ])
        
        # Feedback Endpoints
        feedback_query = "Test query for feedback"
        tests.extend([
            ("Create Feedback", "POST", f"{BASE_URL}/api/rag/feedback",
             {"query": feedback_query, "document_id": doc_id, "rating": 1}),
            ("Get Feedback Stats", "GET", f"{BASE_URL}/api/rag/feedback/stats"),
            ("Get Recent Feedbacks", "GET", f"{BASE_URL}/api/rag/feedback/recent"),
        ])
        
        # Video Endpoints
        tests.extend([
            ("Video Info", "POST", f"{BASE_URL}/api/video/info",
             {"url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"}),
        ])
        
        # Excel Endpoints (only if we have a document)
        if doc_id:
            tests.extend([
                ("Excel Query", "POST", f"{BASE_URL}/api/excel/query",
                 {"document_id": doc_id, "query": "SUM of all values"}),
                ("Excel Structure", "GET", f"{BASE_URL}/api/excel/{doc_id}/structure"),
            ])
        
        # User Endpoints
        tests.extend([
            ("User Info", "GET", f"{BASE_URL}/v1/user/info"),
            ("User Docs List", "GET", f"{BASE_URL}/v1/user/docs/list"),
        ])
        
        # Run all tests
        for test in tests:
            if len(test) == 3:
                name, method, url = test
                result = self.test_endpoint(name, method, url)
            else:
                name, method, url, data = test
                result = self.test_endpoint(name, method, url, data=data)
            
            self.results.append(result)
            time.sleep(0.5)  # Small delay between tests
        
        # Step 4: Test large file upload (optional, with shorter timeout)
        self.print_status("\n📦 Step 3: Testing large file support...", "info")
        large_file = self.create_test_file(
            "Large document test content.\n" * 10000,  # ~200KB
            "large_test.txt"
        )
        
        large_result = self.test_endpoint(
            "Large File Upload",
            "POST",
            f"{BASE_URL}/api/rag/ingest",
            files={"file": ("large_test.txt", open(large_file, "rb"), "text/plain")},
            data={"title": "Large Test Document"},
            timeout=180
        )
        self.results.append(large_result)
        
        # Cleanup
        test_file.unlink(missing_ok=True)
        large_file.unlink(missing_ok=True)
        
        # Print summary
        self.print_summary()
    
    def print_summary(self):
        """Print test summary"""
        print(f"\n{BLUE}{'='*60}{RESET}")
        print(f"{BLUE}TEST SUMMARY{RESET}")
        print(f"{BLUE}{'='*60}{RESET}\n")
        
        total = len(self.results)
        passed = sum(1 for r in self.results if r.get("success"))
        failed = total - passed
        avg_time = sum(r.get("duration", 0) for r in self.results) / total if total > 0 else 0
        
        print(f"Total Tests: {total}")
        print(f"{GREEN}Passed: {passed}{RESET}")
        print(f"{RED}Failed: {failed}{RESET}")
        print(f"Average Time: {avg_time:.2f}s\n")
        
        if failed > 0:
            print(f"{RED}Failed Tests:{RESET}")
            for r in self.results:
                if not r.get("success"):
                    print(f"  - {r['name']}: {r.get('error', 'Unknown error')}")
        
        print(f"\n{BLUE}Detailed Results:{RESET}")
        for r in self.results:
            status = GREEN + "✅" + RESET if r.get("success") else RED + "❌" + RESET
            print(f"  {status} {r['name']}: {r.get('duration', 0):.2f}s - Status {r.get('status_code', 'N/A')}")

if __name__ == "__main__":
    tester = EndpointTester()
    try:
        tester.run_all_tests()
    except KeyboardInterrupt:
        print(f"\n{YELLOW}Test interrupted by user{RESET}")
    except Exception as e:
        print(f"\n{RED}Fatal error: {str(e)}{RESET}")
        import traceback
        traceback.print_exc()

