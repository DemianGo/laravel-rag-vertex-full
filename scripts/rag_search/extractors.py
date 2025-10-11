"""
Extratores de conteúdo para diferentes modos de resposta
Replica funcionalidade do RagAnswerController.php
"""

import re
from typing import List, Dict, Any, Optional

class ContentExtractors:
    """Extratores de conteúdo para diferentes modos"""
    
    @staticmethod
    def extract_numbered_items(text: str) -> Dict[int, str]:
        """
        Extrai itens numerados do texto
        Replica extractNumberedItems() do PHP
        """
        items = {}
        lines = re.split(r'\r?\n', text)
        
        for line in lines:
            line = line.strip()
            if not line:
                continue
                
            # Regex para números seguidos de ponto, parêntese ou hífen
            match = re.match(r'^(\d{1,3})[\.\)\-]?\s+(.*)$', line)
            if match:
                num = int(match.group(1))
                val = match.group(2).strip()
                if val:
                    items[num] = val
        
        # Se encontrou poucos itens, tenta regex mais flexível
        if len(items) < 5:
            pattern = r'(\b\d{1,3})[\.\)\-]?\s+([^\d][^\.]{2,}?)(?=(\b\d{1,3})[\.\)\-]?\s+|$)'
            matches = re.findall(pattern, text, re.DOTALL)
            
            for match in matches:
                num = int(match[0])
                val = match[1].strip()
                if val:
                    items[num] = val
        
        if not items:
            return {}
        
        # Ordena por número e limpa
        sorted_items = dict(sorted(items.items()))
        cleaned_items = {}
        
        for num, val in sorted_items.items():
            # Remove espaços extras e caracteres de pontuação
            val = re.sub(r'\s+', ' ', val)
            val = val.strip(' \t\n\r\0\x0B-–—')
            if val:
                cleaned_items[num] = val
                
        return cleaned_items
    
    @staticmethod
    def extract_key_value_pairs(text: str) -> Dict[str, str]:
        """
        Extrai pares chave-valor do texto
        Replica extractKeyValuePairs() do PHP
        """
        pairs = {}
        lines = re.split(r'\r?\n', text)
        
        for line in lines:
            line = line.strip()
            if len(line) < 4:
                continue
                
            # Regex para chave: valor (suporta dois pontos diferentes)
            match = re.match(r'^\s*([^\:\|]{2,50})\s*[:：]\s*(.+)$', line)
            if match:
                key = match.group(1).strip()
                val = match.group(2).strip()
                if key and val:
                    pairs[key] = val
                    
        return pairs
    
    @staticmethod
    def extract_quote(text: str) -> Optional[str]:
        """
        Extrai citação entre aspas do texto
        Replica extractQuote() do PHP
        """
        # Procura por aspas duplas ou curvas
        patterns = [
            r'[""](.*?)[""]',  # Aspas duplas curvas
            r'"(.*?)"',        # Aspas duplas normais
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.DOTALL)
            if match:
                quote = match.group(1).strip()
                if quote:
                    return f'"{quote}"'
                    
        return None
    
    @staticmethod
    def combine_chunks(chunks: List[Dict]) -> str:
        """
        Combina chunks em texto único
        Replica combineChunks() do PHP
        """
        if not chunks:
            return ""
            
        contents = []
        for chunk in chunks:
            content = chunk.get('content', '')
            if content:
                contents.append(content)
                
        return '\n\n'.join(contents)
    
    @staticmethod
    def combine_chunks_for_list(chunks: List[Dict]) -> str:
        """
        Combina chunks otimizado para listas
        Replica combineChunksForList() do PHP
        """
        if not chunks:
            return ""
            
        # Para listas, preserva quebras de linha importantes
        contents = []
        for chunk in chunks:
            content = chunk.get('content', '')
            if content:
                # Remove quebras excessivas mas preserva estrutura
                content = re.sub(r'\n{3,}', '\n\n', content)
                contents.append(content)
                
        return '\n\n'.join(contents)
    
    @staticmethod
    def load_ord_window(chunks: List[Dict], start_ord: int, end_ord: int) -> List[Dict]:
        """
        Carrega chunks em uma janela de ordem específica
        Replica loadOrdWindow() do PHP
        """
        window_chunks = []
        for chunk in chunks:
            ord_val = chunk.get('ord', 0)
            if start_ord <= ord_val <= end_ord:
                window_chunks.append(chunk)
                
        return window_chunks
    
    @staticmethod
    def load_all_chunks(chunks: List[Dict]) -> List[Dict]:
        """
        Carrega todos os chunks disponíveis
        Replica loadAllChunks() do PHP
        """
        return chunks.copy()
    
    @staticmethod
    def find_best_chunk(chunks: List[Dict]) -> Optional[Dict]:
        """
        Encontra o melhor chunk baseado em similaridade
        """
        if not chunks:
            return None
            
        # Retorna o chunk com maior similaridade
        best_chunk = max(chunks, key=lambda x: x.get('similarity', 0))
        return best_chunk
    
    @staticmethod
    def get_best_ord(chunks: List[Dict]) -> int:
        """
        Retorna a ordem do melhor chunk
        """
        best_chunk = ContentExtractors.find_best_chunk(chunks)
        return best_chunk.get('ord', 0) if best_chunk else 0
