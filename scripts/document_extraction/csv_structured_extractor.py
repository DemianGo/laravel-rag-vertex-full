#!/usr/bin/env python3
"""
CSV Structured Extractor
Extracts both text AND structured JSON data from CSV files.
Similar to Excel extractor but for CSV format.
"""

import sys
import os
import csv
import json
from typing import Dict, List, Any
from pathlib import Path


def extract_csv_structured(file_path: str) -> Dict[str, Any]:
    """
    Extract both text and structured data from CSV.
    
    Returns:
        {
            "success": bool,
            "text": str,
            "structured_data": {
                "headers": [str],
                "rows": [{column: value}],
                "row_count": int,
                "column_count": int
            }
        }
    """
    try:
        if not os.path.exists(file_path):
            return {
                "success": False,
                "error": "File not found",
                "text": "",
                "structured_data": None
            }
        
        # Detect encoding
        import chardet
        with open(file_path, 'rb') as f:
            raw_data = f.read()
            detected = chardet.detect(raw_data)
            encoding = detected['encoding'] or 'utf-8'
        
        # Read CSV
        with open(file_path, 'r', encoding=encoding, errors='replace') as f:
            # Try to detect delimiter
            sample = f.read(1024)
            f.seek(0)
            
            sniffer = csv.Sniffer()
            try:
                delimiter = sniffer.sniff(sample).delimiter
            except:
                delimiter = ','
            
            reader = csv.reader(f, delimiter=delimiter)
            all_rows = list(reader)
        
        if not all_rows:
            return {
                "success": False,
                "error": "Empty CSV file",
                "text": "",
                "structured_data": None
            }
        
        # First row as headers
        headers = [str(cell).strip() for cell in all_rows[0]]
        
        # Build text representation
        text_parts = []
        text_parts.append(' | '.join(headers))
        text_parts.append('-' * 80)
        
        # Build structured data
        structured_rows = []
        
        for row in all_rows[1:]:
            # Text representation
            row_text = ' | '.join(str(cell) for cell in row)
            if row_text.strip():
                text_parts.append(row_text)
            
            # Structured representation
            row_dict = {}
            has_data = False
            for col_idx, cell in enumerate(row):
                if col_idx < len(headers):
                    header = headers[col_idx]
                    value = serialize_value(cell)
                    row_dict[header] = value
                    if value is not None and value != '':
                        has_data = True
            
            if has_data:
                structured_rows.append(row_dict)
        
        return {
            "success": True,
            "text": '\n'.join(text_parts),
            "structured_data": {
                "headers": headers,
                "rows": structured_rows,
                "row_count": len(structured_rows),
                "column_count": len(headers),
                "delimiter": delimiter,
                "encoding": encoding
            },
            "chunking_hints": {
                "chunk_by": "row" if len(structured_rows) < 10000 else "auto",
                "preserve_headers": True
            }
        }
        
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "text": "",
            "structured_data": None
        }


def serialize_value(cell: Any) -> Any:
    """Convert cell value to JSON-serializable type."""
    if cell is None or cell == '':
        return None
    
    cell_str = str(cell).strip()
    
    # Try to parse as number
    try:
        if '.' in cell_str or ',' in cell_str:
            # Try float
            float_val = float(cell_str.replace(',', '.'))
            return float_val
        else:
            # Try int
            return int(cell_str)
    except:
        pass
    
    # Return as string
    return cell_str


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file provided"}))
        sys.exit(1)
    
    file_path = sys.argv[1]
    result = extract_csv_structured(file_path)
    print(json.dumps(result, ensure_ascii=False, indent=2))



