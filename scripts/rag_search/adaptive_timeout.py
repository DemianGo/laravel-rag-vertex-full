#!/usr/bin/env python3
"""
Sistema de timeout adaptativo para processamento de arquivos grandes
Suporta arquivos de até 5000 páginas com timeouts otimizados
"""

import os
import logging
from typing import Dict, Any

logger = logging.getLogger(__name__)

class AdaptiveTimeout:
    """Sistema de timeout adaptativo baseado no tamanho do arquivo"""
    
    @staticmethod
    def calculate_timeout(file_size_bytes: int, default_timeout: int = 120) -> int:
        """
        Calcula timeout adaptativo baseado no tamanho do arquivo
        
        Args:
            file_size_bytes: Tamanho do arquivo em bytes
            default_timeout: Timeout padrão em segundos
            
        Returns:
            Timeout adaptativo em segundos
        """
        file_size_mb = file_size_bytes / (1024 * 1024)
        
        # Timeout baseado no tamanho do arquivo
        if file_size_mb < 1:
            return 15  # 15s para arquivos pequenos (< 1MB)
        elif file_size_mb < 5:
            return 30  # 30s para arquivos pequenos-médios (1-5MB)
        elif file_size_mb < 10:
            return 60  # 1min para arquivos médios (5-10MB)
        elif file_size_mb < 25:
            return 120  # 2min para arquivos médios-grandes (10-25MB)
        elif file_size_mb < 50:
            return 180  # 3min para arquivos grandes (25-50MB)
        elif file_size_mb < 100:
            return 300  # 5min para arquivos grandes (50-100MB)
        elif file_size_mb < 200:
            return 450  # 7.5min para arquivos muito grandes (100-200MB)
        elif file_size_mb < 300:
            return 600  # 10min para arquivos gigantes (200-300MB)
        elif file_size_mb < 400:
            return 750  # 12.5min para arquivos gigantes (300-400MB)
        else:
            return 900  # 15min para arquivos mega (400MB+ até 5000 páginas)
    
    @staticmethod
    def calculate_ocr_timeout(file_size_bytes: int) -> int:
        """
        Calcula timeout específico para OCR baseado no tamanho do arquivo
        OCR é mais lento que extração normal
        
        Args:
            file_size_bytes: Tamanho do arquivo em bytes
            
        Returns:
            Timeout para OCR em segundos
        """
        file_size_mb = file_size_bytes / (1024 * 1024)
        
        # OCR é mais lento, timeouts maiores
        if file_size_mb < 1:
            return 30  # 30s para OCR em arquivos pequenos
        elif file_size_mb < 5:
            return 60  # 1min para OCR em arquivos pequenos-médios
        elif file_size_mb < 10:
            return 120  # 2min para OCR em arquivos médios
        elif file_size_mb < 25:
            return 240  # 4min para OCR em arquivos médios-grandes
        elif file_size_mb < 50:
            return 360  # 6min para OCR em arquivos grandes
        elif file_size_mb < 100:
            return 600  # 10min para OCR em arquivos grandes
        elif file_size_mb < 200:
            return 900  # 15min para OCR em arquivos muito grandes
        elif file_size_mb < 300:
            return 1200  # 20min para OCR em arquivos gigantes
        elif file_size_mb < 400:
            return 1500  # 25min para OCR em arquivos gigantes
        else:
            return 1800  # 30min para OCR em arquivos mega (até 5000 páginas)
    
    @staticmethod
    def calculate_table_extraction_timeout(file_size_bytes: int) -> int:
        """
        Calcula timeout para extração de tabelas baseado no tamanho do arquivo
        
        Args:
            file_size_bytes: Tamanho do arquivo em bytes
            
        Returns:
            Timeout para extração de tabelas em segundos
        """
        file_size_mb = file_size_bytes / (1024 * 1024)
        
        # Extração de tabelas é moderadamente lenta
        if file_size_mb < 1:
            return 20  # 20s para tabelas em arquivos pequenos
        elif file_size_mb < 5:
            return 40  # 40s para tabelas em arquivos pequenos-médios
        elif file_size_mb < 10:
            return 80  # 1.3min para tabelas em arquivos médios
        elif file_size_mb < 25:
            return 150  # 2.5min para tabelas em arquivos médios-grandes
        elif file_size_mb < 50:
            return 240  # 4min para tabelas em arquivos grandes
        elif file_size_mb < 100:
            return 360  # 6min para tabelas em arquivos grandes
        elif file_size_mb < 200:
            return 540  # 9min para tabelas em arquivos muito grandes
        elif file_size_mb < 300:
            return 720  # 12min para tabelas em arquivos gigantes
        elif file_size_mb < 400:
            return 900  # 15min para tabelas em arquivos gigantes
        else:
            return 1080  # 18min para tabelas em arquivos mega (até 5000 páginas)
    
    @staticmethod
    def is_very_large_file(file_size_bytes: int) -> bool:
        """
        Verifica se o arquivo é muito grande e precisa de processamento especial
        
        Args:
            file_size_bytes: Tamanho do arquivo em bytes
            
        Returns:
            True se o arquivo é muito grande
        """
        file_size_mb = file_size_bytes / (1024 * 1024)
        return file_size_mb > 100  # Arquivos > 100MB precisam de processamento especial
    
    @staticmethod
    def estimate_processing_time(file_size_mb: float) -> str:
        """
        Estima tempo de processamento baseado no tamanho do arquivo
        
        Args:
            file_size_mb: Tamanho do arquivo em MB
            
        Returns:
            Estimativa de tempo de processamento
        """
        if file_size_mb < 50:
            return "2-5 minutos"
        elif file_size_mb < 100:
            return "5-10 minutos"
        elif file_size_mb < 200:
            return "10-20 minutos"
        elif file_size_mb < 300:
            return "20-30 minutos"
        elif file_size_mb < 400:
            return "30-45 minutos"
        else:
            return "45-60 minutos"
    
    @staticmethod
    def get_timeout_info(file_size_bytes: int) -> Dict[str, Any]:
        """
        Retorna informações completas sobre timeouts para um arquivo
        
        Args:
            file_size_bytes: Tamanho do arquivo em bytes
            
        Returns:
            Dicionário com informações de timeout
        """
        file_size_mb = file_size_bytes / (1024 * 1024)
        
        return {
            'file_size_mb': round(file_size_mb, 2),
            'is_very_large': AdaptiveTimeout.is_very_large_file(file_size_bytes),
            'estimated_processing_time': AdaptiveTimeout.estimate_processing_time(file_size_mb),
            'timeouts': {
                'extraction': AdaptiveTimeout.calculate_timeout(file_size_bytes),
                'ocr': AdaptiveTimeout.calculate_ocr_timeout(file_size_bytes),
                'tables': AdaptiveTimeout.calculate_table_extraction_timeout(file_size_bytes)
            }
        }

def main():
    """Função principal para teste"""
    print("=== Sistema de Timeout Adaptativo ===")
    
    # Testa diferentes tamanhos de arquivo
    test_sizes = [
        (1024 * 1024, "1MB"),
        (5 * 1024 * 1024, "5MB"),
        (25 * 1024 * 1024, "25MB"),
        (100 * 1024 * 1024, "100MB"),
        (200 * 1024 * 1024, "200MB"),
        (400 * 1024 * 1024, "400MB"),
        (500 * 1024 * 1024, "500MB")
    ]
    
    for size_bytes, size_desc in test_sizes:
        info = AdaptiveTimeout.get_timeout_info(size_bytes)
        print(f"\n📁 Arquivo {size_desc}:")
        print(f"   Extração: {info['timeouts']['extraction']}s")
        print(f"   OCR: {info['timeouts']['ocr']}s")
        print(f"   Tabelas: {info['timeouts']['tables']}s")
        print(f"   Tempo estimado: {info['estimated_processing_time']}")
        if info['is_very_large']:
            print("   ⚠️  Arquivo muito grande - processamento especial necessário")

if __name__ == "__main__":
    main()
