#!/usr/bin/env python3
"""
DOCX Tables Extractor
Extracts tables from Word documents.
"""

import sys
import os
import json
import shutil
import tempfile
from pathlib import Path

try:
    from docx import Document
    DOCX_AVAILABLE = True
except ImportError:
    DOCX_AVAILABLE = False


def extract_docx_tables(file_path: str) -> dict:
    """Extract tables from DOCX file."""
    if not DOCX_AVAILABLE:
        return {
            "success": False,
            "error": "python-docx not available",
            "tables_found": 0,
            "tables": []
        }
    
    temp_link = None
    try:
        path = Path(file_path)
        if not path.exists():
            return {
                "success": False,
                "error": "File not found",
                "tables_found": 0,
                "tables": []
            }
        
        # Handle temp files without extension
        if not file_path.endswith(('.docx', '.doc')):
            temp_file = tempfile.NamedTemporaryFile(suffix='.docx', delete=False)
            temp_file.close()
            shutil.copy2(file_path, temp_file.name)
            file_to_read = temp_file.name
            temp_link = file_to_read
        else:
            file_to_read = file_path
        
        doc = Document(file_to_read)
        tables_data = []
        
        for table_idx, table in enumerate(doc.tables):
            if len(table.rows) < 2:  # Need at least header + 1 row
                continue
            
            # Extract table data
            table_text_lines = []
            table_text_lines.append(f"=== TABELA {table_idx} ===")
            
            # Extract rows
            for row_idx, row in enumerate(table.rows):
                cells = []
                for cell in row.cells:
                    cells.append(cell.text.strip())
                
                row_text = ' | '.join(cells)
                table_text_lines.append(row_text)
                
                # Add separator after header
                if row_idx == 0:
                    table_text_lines.append('-' * min(80, len(row_text)))
            
            table_text = '\n'.join(table_text_lines)
            
            tables_data.append({
                "table_index": table_idx,
                "rows": len(table.rows),
                "cols": len(table.columns) if hasattr(table, 'columns') else 0,
                "text": table_text
            })
        
        return {
            "success": True,
            "tables_found": len(tables_data),
            "tables": tables_data
        }
        
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "tables_found": 0,
            "tables": []
        }
    finally:
        if temp_link and os.path.exists(temp_link):
            try:
                os.remove(temp_link)
            except:
                pass


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file provided"}))
        sys.exit(1)
    
    file_path = sys.argv[1]
    result = extract_docx_tables(file_path)
    print(json.dumps(result, ensure_ascii=False, indent=2))



