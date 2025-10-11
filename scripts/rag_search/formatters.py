"""
Sistema de formatação para diferentes tipos de resposta
Replica funcionalidade do RagAnswerController.php
"""

from typing import List, Dict, Any

class ResponseFormatters:
    """Formatadores de resposta para diferentes tipos"""
    
    @staticmethod
    def format_as(content: str, format_type: str) -> str:
        """
        Formata conteúdo conforme tipo especificado
        Replica formatAs() do PHP
        """
        if not content:
            return content
            
        format_type = format_type.lower()
        
        if format_type == 'markdown':
            return ResponseFormatters._format_markdown(content)
        elif format_type == 'html':
            return ResponseFormatters._format_html(content)
        else:  # plain
            return content.strip()
    
    @staticmethod
    def _format_markdown(content: str) -> str:
        """Formata como Markdown"""
        # Converte quebras de linha duplas em parágrafos
        content = content.strip()
        paragraphs = content.split('\n\n')
        formatted_paragraphs = []
        
        for para in paragraphs:
            para = para.strip()
            if para:
                formatted_paragraphs.append(para)
                
        return '\n\n'.join(formatted_paragraphs)
    
    @staticmethod
    def _format_html(content: str) -> str:
        """Formata como HTML"""
        # Converte quebras de linha em <br> e parágrafos em <p>
        content = content.strip()
        
        # Escapa caracteres HTML
        content = content.replace('&', '&amp;')
        content = content.replace('<', '&lt;')
        content = content.replace('>', '&gt;')
        
        # Converte quebras de linha
        paragraphs = content.split('\n\n')
        formatted_paragraphs = []
        
        for para in paragraphs:
            para = para.strip()
            if para:
                # Converte quebras simples em <br>
                para = para.replace('\n', '<br>')
                formatted_paragraphs.append(f'<p>{para}</p>')
                
        return ''.join(formatted_paragraphs)
    
    @staticmethod
    def render_bullets_simple(items: List[Dict], format_type: str = 'plain') -> str:
        """
        Renderiza lista de bullets
        Replica renderBulletsSimple() do PHP
        """
        if not items:
            return ""
            
        format_type = format_type.lower()
        bullets = []
        
        for item in items:
            if isinstance(item, dict):
                text = item.get('text', '')
            else:
                text = str(item)
                
            if text:
                if format_type == 'markdown':
                    bullets.append(f"- {text}")
                elif format_type == 'html':
                    bullets.append(f"<li>{text}</li>")
                else:  # plain
                    bullets.append(f"- {text}")
        
        if format_type == 'html':
            return f"<ul>{''.join(bullets)}</ul>"
        else:
            return '\n'.join(bullets)
    
    @staticmethod
    def render_table(pairs: Dict[str, str], format_type: str = 'plain') -> str:
        """
        Renderiza tabela de pares chave-valor
        """
        if not pairs:
            return ""
            
        format_type = format_type.lower()
        
        if format_type == 'markdown':
            return ResponseFormatters._render_table_markdown(pairs)
        elif format_type == 'html':
            return ResponseFormatters._render_table_html(pairs)
        else:  # plain
            return ResponseFormatters._render_table_plain(pairs)
    
    @staticmethod
    def _render_table_markdown(pairs: Dict[str, str]) -> str:
        """Renderiza tabela em Markdown"""
        if not pairs:
            return ""
            
        lines = ["| Chave | Valor |", "|-------|-------|"]
        
        for key, value in pairs.items():
            # Escapa pipes no conteúdo
            key = str(key).replace('|', '\\|')
            value = str(value).replace('|', '\\|')
            lines.append(f"| {key} | {value} |")
            
        return '\n'.join(lines)
    
    @staticmethod
    def _render_table_html(pairs: Dict[str, str]) -> str:
        """Renderiza tabela em HTML"""
        if not pairs:
            return ""
            
        rows = []
        for key, value in pairs.items():
            key = str(key).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
            value = str(value).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
            rows.append(f"<tr><td>{key}</td><td>{value}</td></tr>")
            
        return f"<table><tbody>{''.join(rows)}</tbody></table>"
    
    @staticmethod
    def _render_table_plain(pairs: Dict[str, str]) -> str:
        """Renderiza tabela em texto simples"""
        if not pairs:
            return ""
            
        lines = []
        max_key_len = max(len(str(key)) for key in pairs.keys()) if pairs else 0
        
        for key, value in pairs.items():
            key_str = str(key).ljust(max_key_len)
            lines.append(f"{key_str}: {value}")
            
        return '\n'.join(lines)
    
    @staticmethod
    def render_quote(quote: str, format_type: str = 'plain') -> str:
        """
        Renderiza citação
        """
        if not quote:
            return ""
            
        format_type = format_type.lower()
        
        if format_type == 'markdown':
            return f"> {quote}"
        elif format_type == 'html':
            return f"<blockquote>{quote}</blockquote>"
        else:  # plain
            return f'"{quote}"'
    
    @staticmethod
    def ensure_double_quoted(text: str) -> str:
        """
        Garante que o texto está entre aspas duplas
        Replica ensureDoubleQuoted() do PHP
        """
        if not text:
            return '""'
            
        text = text.strip()
        
        # Remove aspas existentes
        text = text.strip('"')
        text = text.strip('"')
        text = text.strip('"')
        text = text.strip('"')
        
        # Adiciona aspas duplas
        return f'"{text}"'
    
    @staticmethod
    def build_direct_rewrite_directive(strictness: int) -> str:
        """
        Constrói diretriz de reescrita para modo DIRECT
        Replica buildDirectRewriteDirective() do PHP
        """
        directives = {
            0: 'Responda de forma fluente e natural, SEM inventar nada e estritamente com base no contexto.',
            1: 'Reescreva de forma clara; pode reorganizar levemente, sem adicionar nada externo.',
            2: 'Reescreva de forma clara e direta, SEM adicionar fatos fora do contexto.',
            3: 'Não use LLM. (fallback extrativo será aplicado)'
        }
        
        return directives.get(strictness, directives[2])
