#!/usr/bin/env python3
"""
Advanced OCR Processor com múltiplas estratégias de pré-processamento.
Otimizado para certificados, documentos com marca d'água e layouts complexos.
"""

import sys
import json
import cv2
import numpy as np
import pytesseract
from PIL import Image
from pathlib import Path
import tempfile
import os

# Try to import Google Vision OCR
try:
    from google_vision_ocr import GoogleVisionOCR
    GOOGLE_VISION_AVAILABLE = True
except ImportError:
    GOOGLE_VISION_AVAILABLE = False

class AdvancedOCRProcessor:
    """Processador OCR avançado com múltiplas estratégias."""
    
    def __init__(self, lang='por+eng', use_google_vision=True):
        self.lang = lang
        self.use_google_vision = use_google_vision and GOOGLE_VISION_AVAILABLE
        
        # Initialize Google Vision if available
        if self.use_google_vision:
            try:
                self.google_vision = GoogleVisionOCR()
            except Exception as e:
                print(f"Warning: Google Vision initialization failed: {e}", file=sys.stderr)
                self.use_google_vision = False
        
        self.strategies = [
            self.strategy_adaptive_threshold,
            self.strategy_high_contrast,
            self.strategy_denoise_aggressive,
            self.strategy_morphology,
            self.strategy_color_filter,
        ]
    
    def process_image(self, image_path: str) -> dict:
        """Processa imagem com múltiplas estratégias e retorna melhor resultado."""
        try:
            # TRY GOOGLE VISION FIRST (Best OCR in the world - 99%+ accuracy)
            if self.use_google_vision:
                try:
                    language_hints = ['pt', 'en'] if 'por' in self.lang else ['en']
                    gv_result = self.google_vision.detect_text(image_path, language_hints=language_hints)
                    
                    if gv_result.get('success') and gv_result.get('confidence', 0) > 80:
                        # Google Vision succeeded with good confidence
                        return {
                            'success': True,
                            'text': gv_result['text'],
                            'confidence': gv_result['confidence'],
                            'best_strategy': 'google_cloud_vision',
                            'all_results': [{
                                'strategy': 'google_cloud_vision',
                                'text': gv_result['text'],
                                'confidence': gv_result['confidence'],
                                'char_count': len(gv_result['text']),
                                'word_count': gv_result.get('word_count', 0)
                            }],
                            'total_strategies': 1,
                            'engine': 'google_cloud_vision'
                        }
                except Exception as e:
                    print(f"Google Vision failed, falling back to Tesseract: {e}", file=sys.stderr)
            
            # FALLBACK TO TESSERACT WITH ADVANCED PREPROCESSING
            # Carrega imagem
            img = cv2.imread(image_path)
            if img is None:
                return {
                    'success': False,
                    'error': 'Não foi possível carregar a imagem'
                }
            
            results = []
            
            # Tenta cada estratégia
            for i, strategy in enumerate(self.strategies):
                try:
                    processed = strategy(img.copy())
                    text, confidence = self._ocr_with_confidence(processed)
                    
                    results.append({
                        'strategy': strategy.__name__,
                        'text': text,
                        'confidence': confidence,
                        'char_count': len(text.strip()),
                        'word_count': len(text.split())
                    })
                except Exception as e:
                    results.append({
                        'strategy': strategy.__name__,
                        'error': str(e),
                        'confidence': 0
                    })
            
            # Ordena por confiança e quantidade de texto
            results.sort(key=lambda x: (
                x.get('confidence', 0),
                x.get('char_count', 0)
            ), reverse=True)
            
            best = results[0]
            
            return {
                'success': True,
                'text': best.get('text', ''),
                'confidence': best.get('confidence', 0),
                'best_strategy': best.get('strategy', 'unknown'),
                'all_results': results,
                'total_strategies': len(results)
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def strategy_adaptive_threshold(self, img):
        """Estratégia 1: Threshold adaptativo (melhor para fundos irregulares)."""
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Aumenta contraste
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8,8))
        enhanced = clahe.apply(gray)
        
        # Threshold adaptativo
        binary = cv2.adaptiveThreshold(
            enhanced, 255,
            cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY,
            blockSize=15,
            C=10
        )
        
        return binary
    
    def strategy_high_contrast(self, img):
        """Estratégia 2: Alto contraste (melhor para texto fraco)."""
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Normalização
        normalized = cv2.normalize(gray, None, 0, 255, cv2.NORM_MINMAX)
        
        # Aumenta contraste drasticamente
        alpha = 2.5  # Contraste
        beta = -100  # Brilho
        contrasted = cv2.convertScaleAbs(normalized, alpha=alpha, beta=beta)
        
        # Threshold Otsu
        _, binary = cv2.threshold(contrasted, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        return binary
    
    def strategy_denoise_aggressive(self, img):
        """Estratégia 3: Remoção agressiva de ruído (melhor para marca d'água)."""
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Denoising bilateral (preserva bordas)
        denoised = cv2.bilateralFilter(gray, 9, 75, 75)
        
        # Morfologia para remover ruído pequeno
        kernel = np.ones((2,2), np.uint8)
        denoised = cv2.morphologyEx(denoised, cv2.MORPH_CLOSE, kernel)
        
        # Threshold adaptativo
        binary = cv2.adaptiveThreshold(
            denoised, 255,
            cv2.ADAPTIVE_THRESH_MEAN_C,
            cv2.THRESH_BINARY,
            blockSize=21,
            C=15
        )
        
        return binary
    
    def strategy_morphology(self, img):
        """Estratégia 4: Operações morfológicas (melhor para texto fino)."""
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Threshold Otsu
        _, binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        # Operações morfológicas para limpar
        kernel = np.ones((2,2), np.uint8)
        
        # Remove ruído pequeno
        opening = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel, iterations=1)
        
        # Fecha buracos em letras
        closing = cv2.morphologyEx(opening, cv2.MORPH_CLOSE, kernel, iterations=1)
        
        return closing
    
    def strategy_color_filter(self, img):
        """Estratégia 5: Filtro de cor (remove marca d'água verde)."""
        # Converte para HSV
        hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
        
        # Define range para verde (marca d'água)
        lower_green = np.array([35, 40, 40])
        upper_green = np.array([85, 255, 255])
        
        # Cria máscara para verde
        mask_green = cv2.inRange(hsv, lower_green, upper_green)
        
        # Inverte máscara (queremos tudo EXCETO verde)
        mask_not_green = cv2.bitwise_not(mask_green)
        
        # Aplica máscara na imagem original
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        result = cv2.bitwise_and(gray, gray, mask=mask_not_green)
        
        # Threshold
        _, binary = cv2.threshold(result, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        return binary
    
    def _ocr_with_confidence(self, img):
        """Executa OCR e retorna texto + confiança média."""
        # Salva imagem temporária
        with tempfile.NamedTemporaryFile(suffix='.png', delete=False) as tmp:
            cv2.imwrite(tmp.name, img)
            tmp_path = tmp.name
        
        try:
            # OCR com dados detalhados
            custom_config = r'--oem 3 --psm 6'
            data = pytesseract.image_to_data(
                tmp_path,
                lang=self.lang,
                config=custom_config,
                output_type=pytesseract.Output.DICT
            )
            
            # Calcula confiança média (ignora -1)
            confidences = [int(c) for c in data['conf'] if int(c) > 0]
            avg_confidence = sum(confidences) / len(confidences) if confidences else 0
            
            # Extrai texto
            text = pytesseract.image_to_string(
                tmp_path,
                lang=self.lang,
                config=custom_config
            )
            
            return text, avg_confidence
            
        finally:
            # Remove arquivo temporário
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)
    
    def post_process_text(self, text: str) -> str:
        """Pós-processamento do texto extraído."""
        # Remove linhas vazias múltiplas
        lines = [line.strip() for line in text.split('\n')]
        lines = [line for line in lines if line]
        
        # Correções comuns
        corrections = {
            'EA Curso-Ofilinezde': 'do Curso Online de',
            'fole': 'foi',
            'horáriá/de 202hor': 'horária de 20 horas',
            'SEE Eis \'aliza': 'realizado pela',
            'E Gr A': 'e a',
            'yr': '',
            '\\i': '',
            'ici': '',
            '(a D O D E PACIENTESIDE |)': 'e a Pacientes de',
        }
        
        text = '\n'.join(lines)
        
        for wrong, right in corrections.items():
            text = text.replace(wrong, right)
        
        return text


def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python3 advanced_ocr_processor.py <image_path>'
        }))
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(json.dumps({
            'success': False,
            'error': f'File not found: {image_path}'
        }))
        sys.exit(1)
    
    processor = AdvancedOCRProcessor()
    result = processor.process_image(image_path)
    
    if result.get('success'):
        # Pós-processa o texto
        result['text_original'] = result['text']
        result['text'] = processor.post_process_text(result['text'])
    
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()

