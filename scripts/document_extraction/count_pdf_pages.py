#!/usr/bin/env python3
import sys
try:
    from PyPDF2 import PdfReader
    reader = PdfReader(sys.argv[1])
    print(len(reader.pages))
except Exception as e:
    sys.exit(1)