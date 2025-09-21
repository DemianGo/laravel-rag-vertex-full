#!/usr/bin/env python3
"""
Sistema de Extração Avançada de PDF para Laravel RAG
"""
import sys
import json
import argparse
import traceback
from pathlib import Path
import fitz  # PyMuPDF
import pdfplumber
from datetime import datetime

class PdfExtractorRAG:
    def __init__(self, pdf_path):
        self.pdf_path = Path(pdf_path)
        self.results = {
            "success": False,
            "timestamp": datetime.now().isoformat(),
            "file_info": {
                "path": str(pdf_path),
                "name": self.pdf_path.name,
                "size_bytes": self.pdf_path.stat().st_size if self.pdf_path.exists() else 0
            },
            "extraction_stats": {
                "total_pages": 0,
                "processed_pages": 0,
                "text_pages": 0,
                "tables_found": 0,
                "images_found": 0,
                "empty_pages": 0
            },
            "content": {
                "full_text": "",
                "pages": [],
                "tables": [],
                "metadata": {}
            },
            "quality_report": {
                "extraction_percentage": 0.0,
                "confidence_score": 0.0,
                "issues": [],
                "pages_needing_review": []
            },
            "errors": []
        }
    
    def extract(self):
        """Executa extração completa do PDF"""
        try:
            if not self.pdf_path.exists():
                raise FileNotFoundError(f"Arquivo não encontrado: {self.pdf_path}")
            
            doc = fitz.open(str(self.pdf_path))
            self.results["extraction_stats"]["total_pages"] = len(doc)
            self.results["content"]["metadata"] = doc.metadata
            
            all_text = []
            
            for page_num in range(len(doc)):
                page = doc[page_num]
                page_data = {
                    "page_number": page_num + 1,
                    "text": "",
                    "has_images": False,
                    "has_tables": False,
                    "extraction_method": "pymupdf",
                    "confidence": 1.0
                }
                
                text = page.get_text()
                if text.strip():
                    page_data["text"] = text
                    all_text.append(text)
                    self.results["extraction_stats"]["text_pages"] += 1
                else:
                    self.results["extraction_stats"]["empty_pages"] += 1
                    self.results["quality_report"]["pages_needing_review"].append({
                        "page": page_num + 1,
                        "reason": "Página sem texto - pode conter imagem escaneada"
                    })
                
                images = page.get_images()
                if images:
                    page_data["has_images"] = True
                    self.results["extraction_stats"]["images_found"] += len(images)
                
                self.results["content"]["pages"].append(page_data)
                self.results["extraction_stats"]["processed_pages"] += 1
            
            self.results["content"]["full_text"] = "\n".join(all_text)
            doc.close()
            
            self._extract_tables_with_pdfplumber()
            self._calculate_quality_metrics()
            self.results["success"] = True
            
        except Exception as e:
            self.results["errors"].append({
                "type": "extraction_error",
                "message": str(e),
                "traceback": traceback.format_exc()
            })
            
        return json.dumps(self.results, ensure_ascii=False, indent=2)
    
    def _extract_tables_with_pdfplumber(self):
        """Extrai tabelas usando pdfplumber"""
        try:
            with pdfplumber.open(str(self.pdf_path)) as pdf:
                for page_num, page in enumerate(pdf.pages):
                    tables = page.extract_tables()
                    if tables:
                        for table_idx, table in enumerate(tables):
                            if table and len(table) > 1:
                                self.results["content"]["tables"].append({
                                    "page": page_num + 1,
                                    "table_index": table_idx,
                                    "rows": len(table),
                                    "cols": len(table[0]) if table else 0,
                                    "data": table
                                })
                                self.results["extraction_stats"]["tables_found"] += 1
                                if page_num < len(self.results["content"]["pages"]):
                                    self.results["content"]["pages"][page_num]["has_tables"] = True
        except Exception as e:
            self.results["errors"].append({
                "type": "table_extraction_warning",
                "message": f"Erro ao extrair tabelas: {str(e)}"
            })
    
    def _calculate_quality_metrics(self):
        """Calcula métricas de qualidade da extração"""
        stats = self.results["extraction_stats"]
        
        if stats["total_pages"] == 0:
            return
        
        extraction_rate = stats["text_pages"] / stats["total_pages"]
        self.results["quality_report"]["extraction_percentage"] = round(extraction_rate * 100, 2)
        
        confidence = extraction_rate
        if stats["empty_pages"] > 0:
            confidence *= 0.9
        if stats["tables_found"] > 0:
            confidence *= 1.05
        
        confidence = min(confidence, 1.0)
        self.results["quality_report"]["confidence_score"] = round(confidence, 2)
        
        percentage = self.results["quality_report"]["extraction_percentage"]
        if percentage == 100:
            status = "EXCELENTE"
            message = f"✅ Extração completa! Todas as {stats['total_pages']} páginas processadas com sucesso."
        elif percentage >= 90:
            status = "BOM"
            message = f"✅ {percentage}% extraído com sucesso. {stats['empty_pages']} páginas podem precisar de OCR."
        elif percentage >= 70:
            status = "ACEITÁVEL"
            message = f"⚠️ {percentage}% extraído. {stats['empty_pages']} páginas precisam de revisão."
        else:
            status = "REVISAR"
            message = f"❌ Apenas {percentage}% extraído. Documento pode estar escaneado ou corrompido."
        
        self.results["quality_report"]["status"] = status
        self.results["quality_report"]["message"] = message
        
        if stats["tables_found"] > 0:
            self.results["quality_report"]["issues"].append(f"📊 {stats['tables_found']} tabelas encontradas")
        if stats["images_found"] > 0:
            self.results["quality_report"]["issues"].append(f"🖼️ {stats['images_found']} imagens detectadas")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Extrai conteúdo de PDF para sistema RAG')
    parser.add_argument('pdf_path', help='Caminho do arquivo PDF')
    parser.add_argument('--verbose', action='store_true', help='Saída detalhada')
    args = parser.parse_args()
    
    if not Path(args.pdf_path).exists():
        print(json.dumps({"success": False, "error": f"Arquivo não encontrado: {args.pdf_path}"}))
        sys.exit(1)
    
    extractor = PdfExtractorRAG(args.pdf_path)
    result = extractor.extract()
    print(result)
