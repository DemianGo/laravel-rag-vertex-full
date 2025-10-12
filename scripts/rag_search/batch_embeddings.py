#!/usr/bin/env python3
"""
Batch Embeddings Generator
Optimized for large documents with 2500+ chunks
Processes embeddings in batches of 100 for 3x speedup
"""

import sys
import json
import os
from typing import List, Dict
from embeddings_service import EmbeddingsService
from database import DatabaseConnection

BATCH_SIZE = 100

def generate_batch_embeddings(document_id: int) -> Dict:
    """
    Generate embeddings for all chunks of a document in batches
    Much faster than one-by-one for large documents
    """
    try:
        db = DatabaseConnection()
        embeddings_service = EmbeddingsService()
        
        # Get all chunks without embeddings for this document
        chunks = db.execute_query("""
            SELECT id, content, chunk_index
            FROM chunks
            WHERE document_id = %s AND embedding IS NULL
            ORDER BY chunk_index
        """, (document_id,))
        
        if not chunks:
            return {
                'success': True,
                'processed': 0,
                'message': 'No chunks need embeddings'
            }
        
        total_chunks = len(chunks)
        processed = 0
        failed = 0
        
        # Process in batches
        for i in range(0, total_chunks, BATCH_SIZE):
            batch = chunks[i:i + BATCH_SIZE]
            batch_texts = [chunk['content'] for chunk in batch]
            batch_ids = [chunk['id'] for chunk in batch]
            
            try:
                # Generate embeddings for entire batch at once
                embeddings = embeddings_service.generate_embeddings_batch(batch_texts)
                
                # Update database
                for chunk_id, embedding in zip(batch_ids, embeddings):
                    if embedding is not None:
                        db.execute_update("""
                            UPDATE chunks
                            SET embedding = %s
                            WHERE id = %s
                        """, (embedding, chunk_id))
                        processed += 1
                    else:
                        failed += 1
                        
            except Exception as e:
                print(f"Batch {i//BATCH_SIZE + 1} failed: {str(e)}", file=sys.stderr)
                
                # Fallback: process individually
                for chunk_id, text in zip(batch_ids, batch_texts):
                    try:
                        embedding = embeddings_service.encode_text(text)
                        if embedding is not None:
                            db.execute_update("""
                                UPDATE chunks
                                SET embedding = %s
                                WHERE id = %s
                            """, (embedding, chunk_id))
                            processed += 1
                        else:
                            failed += 1
                    except:
                        failed += 1
        
        return {
            'success': True,
            'processed': processed,
            'failed': failed,
            'total': total_chunks,
            'message': f'Processed {processed}/{total_chunks} embeddings'
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'Usage: batch_embeddings.py <document_id>'}))
        sys.exit(1)
    
    try:
        document_id = int(sys.argv[1])
        result = generate_batch_embeddings(document_id)
        print(json.dumps(result))
        sys.exit(0 if result['success'] else 1)
    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}))
        sys.exit(1)

if __name__ == '__main__':
    main()

