<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DocumentPageValidator
{
    private const MAX_PAGES = 5000;

    /**
     * Estimate page count for any document type
     * Generic implementation - works for any file without hardcoding
     */
    public function estimatePageCount(string $filePath, string $extension): int
    {
        $extension = strtolower($extension);

        try {
            switch ($extension) {
                case 'pdf':
                    return $this->countPdfPages($filePath);
                
                case 'docx':
                case 'doc':
                    return $this->estimateWordPages($filePath);
                
                case 'xlsx':
                case 'xls':
                case 'csv':
                    return $this->estimateSpreadsheetPages($filePath);
                
                case 'pptx':
                case 'ppt':
                    return $this->countPresentationSlides($filePath);
                
                case 'txt':
                case 'html':
                case 'xml':
                case 'rtf':
                    return $this->estimatePagesBySize($filePath);
                
                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'gif':
                case 'bmp':
                case 'tiff':
                case 'tif':
                case 'webp':
                    return $this->countImagePages($filePath);
                
                default:
                    return $this->estimatePagesBySize($filePath);
            }
        } catch (\Exception $e) {
            Log::warning("Page estimation failed for {$extension}: " . $e->getMessage());
            // Fallback to size-based estimation
            return $this->estimatePagesBySize($filePath);
        }
    }

    /**
     * Validate if document is within page limit
     */
    public function validatePageLimit(string $filePath, string $extension): array
    {
        $estimatedPages = $this->estimatePageCount($filePath, $extension);
        
        if ($estimatedPages > self::MAX_PAGES) {
            return [
                'valid' => false,
                'estimated_pages' => $estimatedPages,
                'max_pages' => self::MAX_PAGES,
                'message' => "Documento muito grande: {$estimatedPages} páginas estimadas. Limite: " . self::MAX_PAGES . " páginas."
            ];
        }

        return [
            'valid' => true,
            'estimated_pages' => $estimatedPages,
            'max_pages' => self::MAX_PAGES,
            'message' => 'Documento dentro do limite permitido.'
        ];
    }

    /**
     * Count PDF pages using Python (fast, exact)
     */
    private function countPdfPages(string $filePath): int
    {
        $pythonScript = base_path('scripts/document_extraction/count_pdf_pages.py');
        
        // Create Python script if doesn't exist
        if (!file_exists($pythonScript)) {
            $this->createPdfPageCounter($pythonScript);
        }

        $cmd = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath) . " 2>/dev/null";
        $output = trim(@shell_exec($cmd) ?? '');
        
        if (is_numeric($output) && $output > 0) {
            return (int) $output;
        }

        // Fallback to size-based estimation
        return $this->estimatePagesBySize($filePath);
    }

    /**
     * Estimate Word document pages
     */
    private function estimateWordPages(string $filePath): int
    {
        $pythonScript = base_path('scripts/document_extraction/count_docx_pages.py');
        
        if (!file_exists($pythonScript)) {
            $this->createDocxPageCounter($pythonScript);
        }

        $cmd = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath) . " 2>/dev/null";
        $output = trim(@shell_exec($cmd) ?? '');
        
        if (is_numeric($output) && $output > 0) {
            return (int) $output;
        }

        // Fallback: 1 page = ~4KB for text documents
        return max(1, (int) ceil(filesize($filePath) / 4096));
    }

    /**
     * Estimate spreadsheet pages (50 rows = 1 page)
     */
    private function estimateSpreadsheetPages(string $filePath): int
    {
        $pythonScript = base_path('scripts/document_extraction/count_xlsx_rows.py');
        
        if (!file_exists($pythonScript)) {
            $this->createXlsxRowCounter($pythonScript);
        }

        $cmd = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath) . " 2>/dev/null";
        $output = trim(@shell_exec($cmd) ?? '');
        
        if (is_numeric($output) && $output > 0) {
            $rows = (int) $output;
            return max(1, (int) ceil($rows / 50)); // 50 rows per page
        }

        // Fallback: 1 page = ~10KB for spreadsheets
        return max(1, (int) ceil(filesize($filePath) / 10240));
    }

    /**
     * Count presentation slides (1 slide = 1 page)
     */
    private function countPresentationSlides(string $filePath): int
    {
        $pythonScript = base_path('scripts/document_extraction/count_pptx_slides.py');
        
        if (!file_exists($pythonScript)) {
            $this->createPptxSlideCounter($pythonScript);
        }

        $cmd = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath) . " 2>/dev/null";
        $output = trim(@shell_exec($cmd) ?? '');
        
        if (is_numeric($output) && $output > 0) {
            return (int) $output;
        }

        // Fallback: 1 page = ~200KB for presentations (with images)
        return max(1, (int) ceil(filesize($filePath) / 204800));
    }

    /**
     * Generic size-based estimation (fallback for any format)
     */
    private function estimatePagesBySize(string $filePath): int
    {
        $sizeBytes = filesize($filePath);
        
        // Generic estimation: 1 page = ~4KB of content
        // This works reasonably well for text-based formats
        return max(1, (int) ceil($sizeBytes / 4096));
    }

    /**
     * Create Python helper: PDF page counter
     */
    private function createPdfPageCounter(string $path): void
    {
        $content = <<<'PYTHON'
#!/usr/bin/env python3
import sys
try:
    from PyPDF2 import PdfReader
    reader = PdfReader(sys.argv[1])
    print(len(reader.pages))
except Exception as e:
    sys.exit(1)
PYTHON;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    /**
     * Create Python helper: DOCX page counter
     */
    private function createDocxPageCounter(string $path): void
    {
        $content = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import os
import tempfile
import shutil
try:
    from docx import Document
    input_file = sys.argv[1]
    
    # Handle files without extension
    if not input_file.endswith('.docx'):
        temp_file = tempfile.NamedTemporaryFile(suffix='.docx', delete=False)
        temp_file.close()
        shutil.copy2(input_file, temp_file.name)
        input_file = temp_file.name
        delete_temp = True
    else:
        delete_temp = False
    
    doc = Document(input_file)
    
    # Count page breaks + estimate from paragraphs
    page_breaks = sum(1 for p in doc.paragraphs if '\\f' in p.text or '\\x0c' in p.text)
    total_paragraphs = len(doc.paragraphs)
    
    # Estimate: 30 paragraphs per page (average)
    estimated_pages = max(page_breaks, total_paragraphs // 30)
    print(max(1, estimated_pages))
    
    if delete_temp and os.path.exists(input_file):
        os.unlink(input_file)
except Exception as e:
    sys.exit(1)
PYTHON;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    /**
     * Create Python helper: XLSX row counter
     */
    private function createXlsxRowCounter(string $path): void
    {
        $content = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import os
import tempfile
import shutil
try:
    from openpyxl import load_workbook
    input_file = sys.argv[1]
    
    # Handle files without extension
    if not input_file.endswith(('.xlsx', '.xls')):
        temp_file = tempfile.NamedTemporaryFile(suffix='.xlsx', delete=False)
        temp_file.close()
        shutil.copy2(input_file, temp_file.name)
        input_file = temp_file.name
        delete_temp = True
    else:
        delete_temp = False
    
    wb = load_workbook(input_file, read_only=True, data_only=True)
    total_rows = sum(sheet.max_row for sheet in wb.worksheets if sheet.max_row)
    print(max(1, total_rows))
    
    if delete_temp and os.path.exists(input_file):
        os.unlink(input_file)
except Exception as e:
    sys.exit(1)
PYTHON;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    /**
     * Create Python helper: PPTX slide counter
     */
    private function createPptxSlideCounter(string $path): void
    {
        $content = <<<'PYTHON'
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
PYTHON;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    /**
     * Count image pages (always 1)
     */
    private function countImagePages(string $filePath): int
    {
        try {
            $scriptPath = base_path('scripts/document_extraction/count_image_pages.py');
            
            // Create script if it doesn't exist
            if (!file_exists($scriptPath)) {
                $this->createImagePageCounter($scriptPath);
            }

            $output = shell_exec("python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($filePath) . " 2>/dev/null");
            $pages = (int)trim($output);
            
            return max(1, $pages); // Images are always at least 1 page
        } catch (\Exception $e) {
            Log::warning("Image page count failed: " . $e->getMessage());
            return 1; // Default to 1 page for images
        }
    }

    /**
     * Create Python helper: Image page counter
     */
    private function createImagePageCounter(string $path): void
    {
        $content = <<<'PYTHON'
#!/usr/bin/env python3
import sys
from pathlib import Path

def count_image_pages(file_path: str) -> int:
    """Images always count as 1 page."""
    try:
        path = Path(file_path)
        if not path.exists():
            return 0
        valid_extensions = ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.tiff', '.tif', '.webp']
        if path.suffix.lower() not in valid_extensions:
            return 0
        return 1
    except:
        return 0

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(1)
    print(count_image_pages(sys.argv[1]))
PYTHON;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }
}

