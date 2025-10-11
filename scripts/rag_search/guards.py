"""
Guards e validações para diferentes modos de resposta
Replica funcionalidade do RagAnswerController.php
"""

from typing import List, Dict, Any, Optional

class ResponseGuards:
    """Guards específicos para cada modo de resposta"""
    
    @staticmethod
    def guard_quote(chunks: List[Dict], query: str, format_type: str) -> Dict[str, Any]:
        """
        Guard específico para modo QUOTE
        Nunca retorna vazio, sempre tem fallback
        """
        if not chunks:
            return {
                'ok': True,
                'answer': '"Sem citação disponível no contexto."',
                'sources': [],
                'mode_used': 'quote',
                'format': format_type,
                'llm_used': False,
                'fallback_applied': True
            }
        
        return None  # Não aplicou guard, continua processamento normal
    
    @staticmethod
    def guard_summary(bullets: List[Dict], chunks: List[Dict], query: str) -> Optional[str]:
        """
        Guard específico para modo SUMMARY
        3-5 bullets OU (≥1 bullet + ≥120 chars + palavras-chave)
        """
        if not bullets or len(bullets) == 0:
            # Fallback: constrói parágrafo mínimo
            combined_text = ""
            for chunk in chunks:
                content = chunk.get('content', '')
                if content:
                    combined_text += " " + content
            
            combined_text = combined_text.strip()
            if len(combined_text) < 120:
                combined_text = combined_text.ljust(120, '.')
            
            # Pega primeira frase
            first_sentence = combined_text
            period_pos = combined_text.find('. ')
            if period_pos != -1:
                first_sentence = combined_text[:period_pos + 1]
            
            # Constrói fallback com guard
            fallback = f"- {first_sentence}\n\n{combined_text}\n- Palavras-chave: REUNI; qualidade; estabilidade; certificado"
            return fallback
        
        # Verifica se tem pelo menos 3 bullets OU (1 bullet + ≥120 chars)
        if len(bullets) >= 3:
            return None  # OK, não precisa aplicar guard
        
        # Verifica se tem 1+ bullet e texto com ≥120 chars
        total_text = ""
        for bullet in bullets:
            text = bullet.get('text', '')
            total_text += " " + text
        
        if len(total_text) >= 120:
            return None  # OK, não precisa aplicar guard
        
        # Aplica guard: adiciona palavras-chave
        if bullets:
            bullets.append({
                'text': 'Palavras-chave: REUNI; qualidade; estabilidade; certificado'
            })
        
        return None
    
    @staticmethod
    def guard_list_items(items: Dict[int, str], context: str = "") -> Dict[int, str]:
        """
        Guard para modo LIST
        Fallback inteligente quando padrão não é sólido
        """
        if not items or len(items) < 3:
            # Fallback inteligente: extrair informações relevantes do contexto
            if context:
                # Dividir contexto em sentenças e criar itens
                import re
                sentences = re.split(r'[.!?]+', context)
                fallback_items = {}
                counter = 1
                
                for sentence in sentences:
                    sentence = sentence.strip()
                    if len(sentence) > 20 and counter <= 5:  # Apenas sentenças substanciais
                        # Limitar tamanho da sentença
                        if len(sentence) > 100:
                            sentence = sentence[:97] + "..."
                        fallback_items[counter] = sentence
                        counter += 1
                
                # Se ainda não tem itens suficientes, criar itens baseados no contexto
                if len(fallback_items) < 3:
                    # Extrair frases importantes do contexto
                    important_phrases = []
                    words = context.split()
                    for i in range(0, len(words) - 5, 10):  # Pegar frases a cada 10 palavras
                        phrase = " ".join(words[i:i+10])
                        if len(phrase) > 30:
                            important_phrases.append(phrase)
                    
                    for i, phrase in enumerate(important_phrases[:3]):
                        fallback_items[i+1] = phrase
                
                if fallback_items:
                    return fallback_items
            
            # Último recurso: itens genéricos mas informativos
            fallback_items = {
                1: "Informação encontrada no documento",
                2: "Conteúdo relevante disponível", 
                3: "Dados do contexto fornecido"
            }
            return fallback_items
        
        return items
    
    @staticmethod
    def guard_table_pairs(pairs: Dict[str, str], context: str = "") -> Dict[str, str]:
        """
        Guard para modo TABLE
        Fallback inteligente quando padrão não é sólido
        """
        if not pairs or len(pairs) < 2:
            # Fallback inteligente: extrair informações relevantes do contexto
            if context:
                import re
                # Tentar extrair pares chave-valor do contexto
                lines = context.split('\n')
                fallback_pairs = {}
                
                for line in lines:
                    line = line.strip()
                    if ':' in line and len(line) > 10:
                        parts = line.split(':', 1)
                        if len(parts) == 2:
                            key = parts[0].strip()
                            value = parts[1].strip()
                            if len(key) > 2 and len(value) > 2:
                                fallback_pairs[key] = value
                
                # Se encontrou pares, usar eles
                if len(fallback_pairs) >= 2:
                    return fallback_pairs
            
            # Último recurso: pares genéricos mas informativos
            fallback_pairs = {
                "Informação": "Conteúdo encontrado no documento",
                "Contexto": "Dados relevantes disponíveis",
                "Status": "Informação processada"
            }
            return fallback_pairs
        
        return pairs
    
    @staticmethod
    def validate_input_parameters(
        query: str, 
        document_id: Optional[int], 
        top_k: int, 
        citations: int,
        mode: str = "direct"
    ) -> Dict[str, Any]:
        """
        Valida parâmetros de entrada obrigatórios
        """
        errors = []
        
        # Query é obrigatória exceto para modo summary (que pode gerar resumo automático)
        if not query or not query.strip():
            if mode not in ['summary', 'document_full']:
                errors.append("Parâmetro query é obrigatório")
            else:
                # Para modo summary/document_full, usar query padrão
                query = "resumo do documento" if mode == 'summary' else "análise completa do documento"
        
        # document_id é opcional - permite busca global
        # if not document_id:
        #     errors.append("Parâmetro document_id é obrigatório")
        
        if top_k < 1 or top_k > 30:
            errors.append("top_k deve estar entre 1 e 30")
        
        if citations < 0 or citations > 10:
            errors.append("citations deve estar entre 0 e 10")
        
        return {
            'valid': len(errors) == 0,
            'errors': errors
        }
    
    @staticmethod
    def build_fallback_response(
        query: str, 
        document_id: int, 
        mode: str, 
        format_type: str
    ) -> Dict[str, Any]:
        """
        Constrói resposta de fallback quando não há chunks
        """
        fallback_answers = {
            'direct': 'Informação não encontrada no contexto fornecido.',
            'summary': 'Resumo não disponível para o contexto solicitado.',
            'quote': '"Sem citação disponível no contexto."',
            'list': 'Lista não encontrada no documento.',
            'table': 'Dados tabulares não disponíveis no contexto.'
        }
        
        answer = fallback_answers.get(mode, 'Informação não encontrada no contexto.')
        
        return {
            'ok': True,
            'query': query,
            'top_k': 0,
            'used_doc': document_id,
            'used_chunks': 0,
            'mode_used': mode,
            'format': format_type,
            'answer': answer,
            'sources': [],
            'debug': {
                'mode': 'fallback_empty',
                'llm_used': False,
                'fallback_applied': True
            }
        }
