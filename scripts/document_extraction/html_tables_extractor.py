#!/usr/bin/env python3
"""
HTML Tables Extractor
Extracts tables from HTML documents using BeautifulSoup.
"""

import sys
import os
import json
from pathlib import Path

try:
    from bs4 import BeautifulSoup
    BS4_AVAILABLE = True
except ImportError:
    BS4_AVAILABLE = False


def extract_html_tables(file_path: str) -> dict:
    """Extract tables from HTML file."""
    if not BS4_AVAILABLE:
        return {
            "success": False,
            "error": "BeautifulSoup not available",
            "tables_found": 0,
            "tables": []
        }
    
    try:
        path = Path(file_path)
        if not path.exists():
            return {
                "success": False,
                "error": "File not found",
                "tables_found": 0,
                "tables": []
            }
        
        with open(file_path, 'r', encoding='utf-8', errors='replace') as f:
            html_content = f.read()
        
        soup = BeautifulSoup(html_content, 'html.parser')
        tables = soup.find_all('table')
        
        tables_data = []
        
        for table_idx, table in enumerate(tables):
            # Extract table rows
            table_text_lines = []
            table_text_lines.append(f"=== TABELA HTML {table_idx} ===")
            
            rows = table.find_all('tr')
            for row_idx, row in enumerate(rows):
                cells = row.find_all(['th', 'td'])
                cell_texts = [cell.get_text(strip=True) for cell in cells]
                
                if cell_texts:
                    row_text = ' | '.join(cell_texts)
                    table_text_lines.append(row_text)
                    
                    # Add separator after first row (usually headers)
                    if row_idx == 0:
                        table_text_lines.append('-' * min(80, len(row_text)))
            
            if len(table_text_lines) > 2:  # Has content beyond header
                table_text = '\n'.join(table_text_lines)
                
                tables_data.append({
                    "table_index": table_idx,
                    "rows": len(rows),
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


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file provided"}))
        sys.exit(1)
    
    file_path = sys.argv[1]
    result = extract_html_tables(file_path)
    print(json.dumps(result, ensure_ascii=False, indent=2))



