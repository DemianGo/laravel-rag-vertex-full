#!/usr/bin/env python3
"""
CSV Extractor Wrapper
Directly extracts CSV files, works with temp files without extensions.
"""

import sys
import os
import csv

def extract_csv(file_path: str) -> str:
    """Extract text from CSV"""
    try:
        if not os.path.exists(file_path):
            return ''
        
        text_parts = []
        
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            reader = csv.reader(f)
            for row in reader:
                row_text = ' | '.join(str(cell).strip() for cell in row if str(cell).strip())
                if row_text:
                    text_parts.append(row_text)
        
        return '\n'.join(text_parts)
    except Exception as e:
        # Silent fail - return empty
        return ''

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(1)
    
    file_path = sys.argv[1]
    text = extract_csv(file_path)
    print(text)

