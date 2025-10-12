#!/usr/bin/env python3
"""
Universal Extractor Wrapper
Calls main_extractor.py for any file type (fallback extractor).
"""

import sys
import json
import subprocess
from pathlib import Path

def extract_universal(file_path: str) -> str:
    """Extract text from any file using main_extractor.py"""
    script_dir = Path(__file__).parent
    main_extractor = script_dir / "main_extractor.py"
    
    try:
        result = subprocess.run(
            ['python3', str(main_extractor), '--input', file_path],
            capture_output=True,
            text=True,
            timeout=30
        )
        
        if result.returncode == 0:
            data = json.loads(result.stdout)
            if data.get('success'):
                return data.get('extracted_text', '')
        
        return ''
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return ''

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: universal_extractor.py <file_path>", file=sys.stderr)
        sys.exit(1)
    
    file_path = sys.argv[1]
    text = extract_universal(file_path)
    print(text)

