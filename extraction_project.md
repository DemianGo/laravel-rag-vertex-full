# Sistema de Extração Universal de Documentos

Crie um sistema completo de extração de documentos para o diretório `/var/www/html/laravel-rag-vertex-full/`. 

Base existente: já tenho `scripts/pdf_extraction/extract_pdf.py` funcionando com PyMuPDF e pdfplumber.

## ARQUIVOS PARA CRIAR:

### 1. scripts/document_extraction/main_extractor.py
Orquestrador principal que:
- Detecta tipo de arquivo usando python-magic ou extensão
- Chama extrator apropriado baseado no tipo
- Padroniza saída JSON
- Trata erros uniformemente

### 2. scripts/document_extraction/extractors/office_extractor.py
Processa DOCX, XLSX, PPTX:
- DOCX: usar python-docx para extrair parágrafos, tabelas, headers, footers
- XLSX: usar openpyxl para extrair todas abas, valores, fórmulas
- PPTX: usar python-pptx para extrair slides, texto, notas
- Retornar métricas: total_elements, extracted, failed

### 3. scripts/document_extraction/extractors/text_extractor.py  
Processa TXT, CSV, HTML, XML:
- Detectar encoding com chardet
- HTML: usar BeautifulSoup para texto limpo
- CSV: detectar delimitador, extrair dados
- Contar linhas/elementos processados

### 4. scripts/document_extraction/quality/analyzer.py
Analisador de qualidade que:
- Calcula extraction_percentage
- Identifica problemas específicos (páginas vazias, tabelas não extraídas)
- Gera recomendações (precisa OCR, encoding incorreto, etc)
- Status: EXCELLENT(>95%), GOOD(>80%), ACCEPTABLE(>60%), POOR(<60%)

### 5. app/Services/UniversalDocumentExtractor.php
```php
<?php
namespace App\Services;

class UniversalDocumentExtractor {
    public function extract($filePath) {
        // Chama python3 scripts/document_extraction/main_extractor.py $filePath
        // Retorna array com resultado parseado
    }
    
    public function getSupportedFormats() {
        return ['pdf','docx','xlsx','pptx','txt','csv','html','xml'];
    }
}
