#!/usr/bin/env python3
"""Quick endpoint tester with proper status codes"""
import requests
import json
import time
from pathlib import Path

BASE_URL = "http://localhost:8002"
TIMEOUT = 60

GREEN = "\033[92m"
RED = "\033[91m"
YELLOW = "\033[93m"
BLUE = "\033[94m"
RESET = "\033[0m"

def test_endpoint(name, method, url, headers=None, data=None, files=None, expected_status=200, timeout=TIMEOUT):
    """Test a single endpoint"""
    try:
        if method == "GET":
            r = requests.get(url, headers=headers, timeout=timeout)
        elif method == "POST":
            if files:
                r = requests.post(url, headers=headers, files=files, data=data, timeout=timeout)
            else:
                if not headers:
                    headers = {}
                headers["Content-Type"] = "application/json"
                r = requests.post(url, headers=headers, json=data, timeout=timeout)
        else:
            return False, f"Unknown method: {method}"
        
        success = r.status_code == expected_status
        status = f"{GREEN}✅{RESET}" if success else f"{RED}❌{RESET}"
        print(f"  {status} {name}: {r.status_code} ({expected_status} expected) - {r.elapsed.total_seconds():.2f}s")
        return success, r
    except requests.exceptions.Timeout:
        print(f"  {RED}⏱️{RESET} {name}: TIMEOUT after {TIMEOUT}s")
        return False, None
    except Exception as e:
        print(f"  {RED}❌{RESET} {name}: Error - {str(e)}")
        return False, None

# Get API key
print(f"{BLUE}=== FASTAPI ENDPOINTS TEST ==={RESET}\n")
print(f"{BLUE}Step 1: Authentication...{RESET}")

auth_resp = requests.post(
    f"{BASE_URL}/auth/register",
    json={"email": f"test_{int(time.time())}@example.com", "password": "Test123!", "name": "Test"},
    timeout=10
)
api_key = auth_resp.json().get("api_key") if auth_resp.status_code == 200 else None
headers = {"X-API-Key": api_key} if api_key else {}

if not api_key:
    print(f"{RED}❌ Failed to get API key{RESET}")
    exit(1)

print(f"{GREEN}✅ API Key: {api_key[:30]}...{RESET}\n")

# Upload test document
print(f"{BLUE}Step 2: Upload test document...{RESET}")
test_file = Path("/tmp/test_doc.txt")
test_file.write_text("Test document content\n" * 100)

success, upload_resp = test_endpoint(
    "Upload Document",
    "POST",
    f"{BASE_URL}/api/rag/ingest",
    headers=headers,
    files={"file": ("test.txt", open(test_file, "rb"), "text/plain")},
    data={"title": "Test Document"},
    expected_status=201
)

doc_id = None
if success and upload_resp:
    try:
        doc_id = upload_resp.json().get("document_id")
    except:
        pass

test_file.unlink()

print(f"\n{BLUE}Step 3: Testing RAG endpoints...{RESET}")

if doc_id:
    test_endpoint("Get Chunks", "GET", f"{BASE_URL}/api/rag/docs/{doc_id}/chunks", headers=headers)
    test_endpoint("Generate Embeddings", "POST", f"{BASE_URL}/api/rag/embeddings/generate", 
                  headers=headers, data={"document_id": doc_id}, expected_status=200)
else:
    print(f"  {YELLOW}⚠️ Skipping chunks/embeddings (no doc_id){RESET}")

print(f"\n{BLUE}Step 4: Testing Feedback endpoints...{RESET}")
test_endpoint("Create Feedback", "POST", f"{BASE_URL}/api/rag/feedback",
              headers=headers, data={"query": "Test query", "rating": 1, "document_id": doc_id})
test_endpoint("Get Feedback Stats", "GET", f"{BASE_URL}/api/rag/feedback/stats", headers=headers)
test_endpoint("Get Recent Feedbacks", "GET", f"{BASE_URL}/api/rag/feedback/recent", headers=headers)

print(f"\n{BLUE}Step 5: Testing Video endpoints...{RESET}")
test_endpoint("Video Info", "POST", f"{BASE_URL}/api/video/info",
              headers=headers, data={"url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"}, timeout=30)

print(f"\n{BLUE}Step 6: Testing Excel endpoints...{RESET}")
if doc_id:
    test_endpoint("Excel Query", "POST", f"{BASE_URL}/api/excel/query",
                  headers=headers, data={"document_id": doc_id, "query": "SUM test"})
    test_endpoint("Excel Structure", "GET", f"{BASE_URL}/api/excel/{doc_id}/structure", headers=headers)
else:
    print(f"  {YELLOW}⚠️ Skipping Excel (no doc_id){RESET}")

print(f"\n{BLUE}Step 7: Testing User endpoints...{RESET}")
test_endpoint("User Info", "GET", f"{BASE_URL}/v1/user/info", headers=headers)
test_endpoint("User Docs List", "GET", f"{BASE_URL}/v1/user/docs/list", headers=headers)
if doc_id:
    test_endpoint("User Doc Details", "GET", f"{BASE_URL}/v1/user/docs/{doc_id}", headers=headers)

print(f"\n{BLUE}Step 8: Testing large file (200KB)...{RESET}")
large_file = Path("/tmp/large_test.txt")
large_file.write_text("Large document test content.\n" * 5000)  # ~200KB

success, _ = test_endpoint(
    "Large File Upload",
    "POST",
    f"{BASE_URL}/api/rag/ingest",
    headers=headers,
    files={"file": ("large.txt", open(large_file, "rb"), "text/plain")},
    data={"title": "Large Test"},
    expected_status=201
)

large_file.unlink()

print(f"\n{GREEN}✅ All tests completed!{RESET}")
