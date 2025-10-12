#!/usr/bin/env python3
"""
Count pages for image files.
Images always count as 1 page.
"""

import sys
from pathlib import Path

def count_image_pages(file_path: str) -> int:
    """
    Count pages in an image file.
    Images always count as 1 page.
    
    Args:
        file_path: Path to the image file
        
    Returns:
        1 (images are always considered 1 page)
    """
    try:
        path = Path(file_path)
        
        # Verify file exists
        if not path.exists():
            return 0
            
        # Verify it's an image extension
        valid_extensions = ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.tiff', '.tif', '.webp']
        if path.suffix.lower() not in valid_extensions:
            return 0
            
        # Images are always 1 page
        return 1
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: count_image_pages.py <file_path>", file=sys.stderr)
        sys.exit(1)
    
    file_path = sys.argv[1]
    page_count = count_image_pages(file_path)
    print(page_count)

