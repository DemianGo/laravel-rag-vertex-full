#!/usr/bin/env python3
import sys
import os
import tempfile
import shutil
try:
    from pptx import Presentation
    input_file = sys.argv[1]
    
    # Handle files without extension
    if not input_file.endswith(('.pptx', '.ppt')):
        temp_file = tempfile.NamedTemporaryFile(suffix='.pptx', delete=False)
        temp_file.close()
        shutil.copy2(input_file, temp_file.name)
        input_file = temp_file.name
        delete_temp = True
    else:
        delete_temp = False
    
    prs = Presentation(input_file)
    print(len(prs.slides))
    
    if delete_temp and os.path.exists(input_file):
        os.unlink(input_file)
except Exception as e:
    sys.exit(1)