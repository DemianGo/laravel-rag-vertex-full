#!/usr/bin/env python3
"""
RTF Extractor Wrapper
Extracts RTF files using text_extractor.py or striprtf.
"""

import sys
import os
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent))

def extract_rtf(file_path: str) -> str:
    """Extract text from RTF"""
    try:
        if not os.path.exists(file_path):
            return ''
        
        # Try striprtf library if available
        try:
            from striprtf.striprtf import rtf_to_text
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                rtf_content = f.read()
            return rtf_to_text(rtf_content)
        except ImportError:
            pass
        
        # Fallback: basic RTF stripping
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Remove RTF control words (basic)
        import re
        text = re.sub(r'\\[a-z]+\d*\s?', ' ', content)
        text = re.sub(r'[{}]', '', text)
        text = re.sub(r'\s+', ' ', text)
        
        return text.strip()
    except Exception as e:
        # Silent fail - return empty
        return ''

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(1)
    
    file_path = sys.argv[1]
    text = extract_rtf(file_path)
    print(text)

