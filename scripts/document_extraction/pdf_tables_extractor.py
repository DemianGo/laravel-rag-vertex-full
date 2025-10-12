#!/usr/bin/env python3
"""
PDF Tables Extractor
Extracts tables from PDF using pdfplumber.
Lightweight wrapper for RagController integration.
"""

import sys
import json
from pathlib import Path

try:
    import pdfplumber
    PDFPLUMBER_AVAILABLE = True
except ImportError:
    PDFPLUMBER_AVAILABLE = False


def extract_tables_from_pdf(pdf_path: str) -> dict:
    """
    Extract tables from PDF file.
    
    Returns:
        {
            "success": bool,
            "tables_found": int,
            "tables": [
                {
                    "page": int,
                    "table_index": int,
                    "rows": int,
                    "cols": int,
                    "data": [[cell values]],
                    "text": "formatted table as text"
                }
            ]
        }
    """
    if not PDFPLUMBER_AVAILABLE:
        return {
            "success": False,
            "error": "pdfplumber not available",
            "tables_found": 0,
            "tables": []
        }
    
    try:
        path = Path(pdf_path)
        if not path.exists():
            return {
                "success": False,
                "error": "File not found",
                "tables_found": 0,
                "tables": []
            }
        
        tables_data = []
        
        with pdfplumber.open(pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages, start=1):
                # Extract tables from this page
                tables = page.extract_tables()
                
                if tables:
                    for table_idx, table in enumerate(tables):
                        if table and len(table) > 1:  # At least header + 1 row
                            # Format table as text
                            table_text = format_table_as_text(table, page_num, table_idx)
                            
                            tables_data.append({
                                "page": page_num,
                                "table_index": table_idx,
                                "rows": len(table),
                                "cols": len(table[0]) if table else 0,
                                "data": table,
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


def format_table_as_text(table: list, page_num: int, table_idx: int) -> str:
    """
    Format table as readable text for RAG chunking.
    
    Example:
    === TABELA (Página 1, Tabela 0) ===
    Header1 | Header2 | Header3
    Value1  | Value2  | Value3
    """
    if not table or len(table) < 1:
        return ""
    
    lines = []
    lines.append(f"=== TABELA (Página {page_num}, Tabela {table_idx}) ===")
    
    # Process each row
    for row_idx, row in enumerate(table):
        # Clean cells (remove None, strip whitespace)
        cleaned_row = [str(cell).strip() if cell is not None else '' for cell in row]
        
        # Join with separator
        row_text = ' | '.join(cleaned_row)
        
        if row_text.strip():
            lines.append(row_text)
            
            # Add separator after header (first row)
            if row_idx == 0:
                lines.append('-' * min(80, len(row_text)))
    
    return '\n'.join(lines)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file provided"}))
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    result = extract_tables_from_pdf(pdf_path)
    print(json.dumps(result, ensure_ascii=False, indent=2))

