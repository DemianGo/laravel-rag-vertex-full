#!/usr/bin/env python3
import psycopg2

conn = psycopg2.connect(
    dbname="laravel_rag_db",
    user="raguser_new",
    password="senhasegura123",
    host="127.0.0.1",
    port="5432"
)

cursor = conn.cursor()

# Verificar documento
cursor.execute("SELECT id, title, source, created_at FROM documents WHERE id = 404")
doc = cursor.fetchone()
print(f"\n📄 DOCUMENTO:")
print(f"ID: {doc[0]}")
print(f"Title: {doc[1]}")
print(f"Source: {doc[2]}")
print(f"Created: {doc[3]}")

# Verificar chunks
cursor.execute("SELECT id, ord, content FROM chunks WHERE document_id = 404 ORDER BY ord")
chunks = cursor.fetchall()
print(f"\n📦 CHUNKS: {len(chunks)} chunks encontrados")
for chunk in chunks:
    print(f"  - Chunk {chunk[1]} (ID: {chunk[0]}): {len(chunk[2])} chars")

cursor.close()
conn.close()
