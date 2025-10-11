"""
Detector de modos de resposta baseado na query do usuário
Replica funcionalidade do RagAnswerController.php
"""

import re
from typing import List

class ModeDetector:
    """Detecta o modo de resposta baseado na query"""
    
    @staticmethod
    def detect_mode(param: str, query: str) -> str:
        """
        Detecta o modo de resposta baseado no parâmetro e query
        
        Args:
            param: Parâmetro mode explícito
            query: Query do usuário
            
        Returns:
            Modo detectado: auto, direct, summary, quote, list, table, document_full
        """
        param = param.lower() if param else 'auto'
        valid_modes = ['auto', 'direct', 'list', 'summary', 'quote', 'table', 'document_full']
        
        if param in valid_modes and param != 'auto':
            return param
            
        if param == 'auto':
            query_lower = query.lower()
            
            if ModeDetector.has_list_intent(query_lower):
                return 'list'
            if ModeDetector.has_summary_intent(query_lower):
                return 'summary'
            if ModeDetector.has_quote_intent(query_lower):
                return 'quote'
            if ModeDetector.has_table_intent(query_lower):
                return 'table'
            if ModeDetector.has_document_full_intent(query_lower):
                return 'document_full'
                
        return 'direct'
    
    @staticmethod
    def has_list_intent(query: str) -> bool:
        """Detecta intent de lista"""
        hints = [
            'lista', 'listar', 'list ', 'enumerar', 'enumera', 'enumere',
            'numerando', 'uma a uma', 'um a um', 'todas', 'todos', 'sem resumir'
        ]
        
        for hint in hints:
            if hint in query:
                return True
                
        # Regex para detectar "list" como palavra completa
        if re.search(r'\blist\b', query, re.IGNORECASE):
            return True
            
        return False
    
    @staticmethod
    def has_summary_intent(query: str) -> bool:
        """Detecta intent de resumo"""
        hints = [
            'resumo', 'resuma', 'resumir', 'sumário', 'executivo',
            'em linhas', 'síntese', 'sintese'
        ]
        
        for hint in hints:
            if hint in query:
                return True
                
        return False
    
    @staticmethod
    def has_quote_intent(query: str) -> bool:
        """Detecta intent de citação"""
        hints = [
            'citação', 'cite a frase', 'frase exata', 'trecho exato',
            'entre aspas', 'o que disse', 'o que afirma'
        ]
        
        for hint in hints:
            if hint in query:
                return True
                
        return False
    
    @staticmethod
    def has_table_intent(query: str) -> bool:
        """Detecta intent de tabela"""
        hints = [
            'tabela', 'tabelar', 'colunas', 'chave valor', 'key value',
            'kv', 'preços', 'planos', 'especificações', 'especificacao'
        ]
        
        for hint in hints:
            if hint in query:
                return True
                
        return False
    
    @staticmethod
    def has_document_full_intent(query: str) -> bool:
        """Detecta intent de análise completa do documento"""
        hints = [
            'todo o documento', 'documento completo', 'arquivo inteiro', 'arquivo completo',
            'todo o arquivo', 'análise completa', 'analise completa', 'resumo completo',
            'documento inteiro', 'arquivo todo', 'tudo do documento', 'conteúdo completo',
            'conteudo completo', 'todo o conteúdo', 'todo o conteudo', 'análise do documento',
            'analise do documento', 'explicação completa', 'explicacao completa'
        ]
        
        for hint in hints:
            if hint in query:
                return True
                
        return False

class ParameterNormalizer:
    """Normaliza parâmetros de entrada"""
    
    @staticmethod
    def normalize_length(length: str) -> str:
        """Normaliza parâmetro length"""
        valid_lengths = ['auto', 'short', 'medium', 'long', 'xl']
        return length.lower() if length.lower() in valid_lengths else 'auto'
    
    @staticmethod
    def normalize_format(format: str) -> str:
        """Normaliza parâmetro format"""
        valid_formats = ['plain', 'markdown', 'html']
        return format.lower() if format.lower() in valid_formats else 'plain'
    
    @staticmethod
    def validate_citations(citations: int) -> int:
        """Valida número de citações"""
        return max(0, min(10, int(citations)))
    
    @staticmethod
    def validate_top_k(top_k: int) -> int:
        """Valida top_k"""
        return max(1, min(30, int(top_k)))
