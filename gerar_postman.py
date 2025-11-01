#!/usr/bin/env python3
import json

with open('/tmp/openapi.json') as f:
    spec = json.load(f)

collection = {
    "info": {
        "name": "RAG System API - FastAPI Complete",
        "description": "All endpoints migrated from Laravel to FastAPI",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "auth": {
        "type": "apikey",
        "apikey": [
            {"key": "value", "value": "{{api_key}}", "type": "string"},
            {"key": "key", "value": "X-API-Key", "type": "string"}
        ]
    },
    "variable": [
        {"key": "base_url", "value": "http://localhost:8002", "type": "string"},
        {"key": "api_key", "value": "rag_abc123dev", "type": "string"}
    ],
    "item": []
}

def add_item(item_list, path, method, details):
    """Add endpoint to collection"""
    item = {
        "name": f"{method.upper()} {path}",
        "request": {
            "method": method.upper(),
            "header": [
                {"key": "X-API-Key", "value": "{{api_key}}", "type": "text"}
            ],
            "url": {
                "raw": "{{base_url}}" + path,
                "host": ["{{base_url}}"],
                "path": [p for p in path.split("/") if p]
            },
            "description": details.get('description', '')
        }
    }
    
    if method in ['post', 'put', 'patch']:
        if 'multipart' in path or 'ingest' in path:
            item["request"]["body"] = {
                "mode": "formdata",
                "formdata": [
                    {"key": "file", "type": "file", "src": "/tmp/test.txt"},
                    {"key": "title", "value": "Test Document", "type": "text"}
                ]
            }
        else:
            item["request"]["header"].append({
                "key": "Content-Type",
                "value": "application/json",
                "type": "text"
            })
            item["request"]["body"] = {
                "mode": "raw",
                "raw": "{\n  \"query\": \"test query\",\n  \"document_id\": 1\n}"
            }
    
    item_list.append(item)

# Group endpoints
groups = {}
for path, methods in spec['paths'].items():
    for method, details in methods.items():
        if method in ['get', 'post', 'put', 'delete', 'patch']:
            group = path.split('/')[1] if '/' in path else 'root'
            if group not in groups:
                groups[group] = []
            add_item(groups[group], path, method, details)

# Sort groups
for group in sorted(groups.keys()):
    collection["item"].append({
        "name": group.capitalize(),
        "item": groups[group]
    })

with open('postman_collection_RAG_API_FULL.json', 'w') as f:
    json.dump(collection, f, indent=2)

print(f"✅ Postman collection created: postman_collection_RAG_API_FULL.json")
print(f"   Total endpoints: {sum(len(items) for items in groups.values())}")
