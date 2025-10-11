#!/usr/bin/env python3
"""
Question Suggester - Gerador de Perguntas Sugeridas
Analisa documento e gera perguntas relevantes automaticamente
Roda APÓS upload bem-sucedido
"""

import sys
import json
import argparse
from pathlib import Path
from typing import Dict, Any, Optional, List

# Fix para imports
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

try:
    from database import DatabaseManager
    from config import Config
except ImportError as e:
    error_response = {
        "success": False,
        "error": f"Erro ao importar módulos: {str(e)}"
    }
    print(json.dumps(error_response, ensure_ascii=False, indent=2))
    sys.exit(1)


class QuestionSuggester:
    """
    Gera perguntas sugeridas baseadas no tipo de documento
    """
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        self.db_manager = DatabaseManager(db_config)
        self.config = Config(db_config) if db_config else Config()
    
    def generate_suggestions(self, document_id: int) -> Dict[str, Any]:
        """
        Gera perguntas sugeridas para um documento
        
        Returns:
            {
                "success": True,
                "document_id": 142,
                "document_type": "medical",
                "suggestions": ["pergunta 1", "pergunta 2", ...],
                "saved": True
            }
        """
        
        print(f"[SUGGESTER] Gerando perguntas para documento {document_id}", file=sys.stderr)
        
        try:
            # 1. Busca informações do documento
            doc_query = """
                SELECT id, title
                FROM documents
                WHERE id = %s
            """
            
            result = self.db_manager.execute_query(doc_query, [document_id])
            
            if not result:
                return {
                    "success": False,
                    "error": f"Documento {document_id} não encontrado"
                }
            
            doc = result[0]
            title = doc.get('title', '') if isinstance(doc, dict) else (doc[1] if len(doc) > 1 else '')
            
            # 2. Detecta tipo do documento
            doc_type = self._detect_document_type(title, document_id)
            print(f"[SUGGESTER] Tipo detectado: {doc_type}", file=sys.stderr)
            
            # 3. Gera perguntas baseadas no tipo
            suggestions = self._generate_by_type(doc_type, title, document_id)
            print(f"[SUGGESTER] Geradas {len(suggestions)} perguntas", file=sys.stderr)
            
            # 4. Salva no metadata do documento
            saved = self._save_suggestions(document_id, suggestions, doc_type)
            
            return {
                "success": True,
                "document_id": document_id,
                "document_type": doc_type,
                "suggestions": suggestions,
                "saved": saved
            }
        
        except Exception as e:
            print(f"[SUGGESTER] Erro: {e}", file=sys.stderr)
            return {
                "success": False,
                "error": str(e)
            }
    
    def _detect_document_type(self, title: str, document_id: int) -> str:
        """
        Detecta tipo do documento baseado no título e conteúdo
        """
        title_lower = title.lower()
        
        # Médico/Farmacêutico
        medical_keywords = [
            'bula', 'medicamento', 'remédio', 'farmaco', 'fármaco',
            'dosagem', 'posologia', 'cannabis', 'cbd', 'thc',
            'tratamento', 'terapia', 'protocolo', 'paciente'
        ]
        if any(kw in title_lower for kw in medical_keywords):
            return 'medical'
        
        # Jurídico
        legal_keywords = [
            'contrato', 'acordo', 'termo', 'lei', 'artigo',
            'cláusula', 'clausula', 'jurisprudência', 'jurisprudencia',
            'petição', 'peticao', 'parecer', 'sentença', 'sentenca'
        ]
        if any(kw in title_lower for kw in legal_keywords):
            return 'legal'
        
        # Acadêmico
        academic_keywords = [
            'artigo', 'paper', 'estudo', 'pesquisa', 'análise', 'analise',
            'tese', 'dissertação', 'dissertacao', 'monografia',
            'revista', 'journal', 'metodologia'
        ]
        if any(kw in title_lower for kw in academic_keywords):
            return 'academic'
        
        # Comercial/Catálogo
        commercial_keywords = [
            'catálogo', 'catalogo', 'produto', 'preço', 'preco',
            'especificação', 'especificacao', 'manual', 'guia',
            'venda', 'compra', 'oferta'
        ]
        if any(kw in title_lower for kw in commercial_keywords):
            return 'commercial'
        
        # Educacional
        educational_keywords = [
            'apostila', 'aula', 'curso', 'livro', 'capítulo', 'capitulo',
            'exercício', 'exercicio', 'lição', 'licao', 'tutorial'
        ]
        if any(kw in title_lower for kw in educational_keywords):
            return 'educational'
        
        # Tenta detectar pelo conteúdo (primeiros chunks)
        try:
            content_query = """
                SELECT content FROM chunks
                WHERE document_id = %s
                ORDER BY ord ASC
                LIMIT 3
            """
            chunks = self.db_manager.execute_query(content_query, [document_id])
            
            if chunks:
                sample_text = ' '.join([c[0][:500] for c in chunks]).lower()
                
                # Re-verifica com conteúdo
                if any(kw in sample_text for kw in medical_keywords):
                    return 'medical'
                elif any(kw in sample_text for kw in legal_keywords):
                    return 'legal'
                elif any(kw in sample_text for kw in academic_keywords):
                    return 'academic'
        except:
            pass
        
        # Default: genérico
        return 'generic'
    
    def _generate_by_type(
        self, 
        doc_type: str, 
        title: str,
        document_id: int
    ) -> List[str]:
        """
        Gera perguntas baseadas no tipo de documento
        """
        
        if doc_type == 'medical':
            return [
                "Quais são as indicações deste medicamento?",
                "Qual a dosagem recomendada?",
                "Quais são as contraindicações?",
                "Quais os efeitos colaterais?",
                "Como deve ser armazenado?",
                "Quais as interações medicamentosas?",
                "Qual o mecanismo de ação?",
                "Há restrições para uso em crianças ou idosos?"
            ]
        
        elif doc_type == 'legal':
            return [
                "Quais são as partes envolvidas?",
                "Qual o prazo de vigência?",
                "Quais as condições de rescisão?",
                "Qual o valor ou valores envolvidos?",
                "Quais as penalidades previstas?",
                "Quais as obrigações de cada parte?",
                "Há cláusulas de confidencialidade?",
                "Como são resolvidos os conflitos?"
            ]
        
        elif doc_type == 'academic':
            return [
                "Qual o objetivo principal do estudo?",
                "Qual a metodologia utilizada?",
                "Quais foram os principais resultados?",
                "Quais são as conclusões?",
                "Quais as limitações do estudo?",
                "Qual a relevância prática?",
                "Quais trabalhos futuros são sugeridos?",
                "Quais as referências principais?"
            ]
        
        elif doc_type == 'commercial':
            return [
                "Quais produtos estão disponíveis?",
                "Quais os preços?",
                "Quais as especificações técnicas?",
                "Quais os diferenciais competitivos?",
                "Qual o prazo de entrega?",
                "Há garantia? Qual o período?",
                "Quais as formas de pagamento?",
                "Como entrar em contato?"
            ]
        
        elif doc_type == 'educational':
            return [
                "Quais são os principais conceitos?",
                "Quais os objetivos de aprendizado?",
                "Há exemplos práticos?",
                "Quais os exercícios propostos?",
                "Qual a bibliografia recomendada?",
                "Há resumo dos capítulos?",
                "Quais os pré-requisitos?",
                "Como aplicar na prática?"
            ]
        
        else:  # generic
            return [
                "Faça um resumo deste documento",
                "Quais são os pontos principais?",
                "Sobre o que trata este documento?",
                "Quais as informações mais importantes?",
                "Há conclusões ou recomendações?",
                "Quais dados numéricos são apresentados?",
                "Há tabelas ou listas importantes?",
                "Quem é o público-alvo?"
            ]
    
    def _save_suggestions(
        self,
        document_id: int,
        suggestions: List[str],
        doc_type: str
    ) -> bool:
        """
        Salva perguntas sugeridas no metadata do documento
        """
        try:
            # Cria novo metadata com perguntas
            metadata = {
                'suggested_questions': suggestions,
                'document_type': doc_type,
                'suggestions_generated_at': self._get_timestamp()
            }
            
            # Atualiza no banco (COALESCE para mesclar com metadata existente)
            update_query = """
                UPDATE documents 
                SET metadata = COALESCE(metadata::jsonb, '{}'::jsonb) || %s::jsonb
                WHERE id = %s
            """
            
            with self.db_manager.get_connection() as conn:
                with conn.cursor() as cursor:
                    cursor.execute(update_query, [json.dumps(metadata), document_id])
                    conn.commit()
            
            print(f"[SUGGESTER] Perguntas salvas no metadata do documento {document_id}", file=sys.stderr)
            return True
        
        except Exception as e:
            print(f"[SUGGESTER] Erro ao salvar: {e}", file=sys.stderr)
            return False
    
    def _get_timestamp(self) -> str:
        """Retorna timestamp atual em formato ISO"""
        from datetime import datetime
        return datetime.now().isoformat()


def main():
    """
    CLI do Question Suggester
    """
    parser = argparse.ArgumentParser(description='Question Suggester - Gerador de Perguntas')
    parser.add_argument('--document-id', type=int, required=True, help='ID do documento')
    parser.add_argument('--db-config', help='Configuração do banco (JSON)')
    
    args = parser.parse_args()
    
    # Parse db_config
    db_config = None
    if args.db_config:
        try:
            db_config = json.loads(args.db_config)
        except:
            pass
    
    # Gera sugestões
    suggester = QuestionSuggester(db_config)
    result = suggester.generate_suggestions(args.document_id)
    
    # Retorna resultado
    print(json.dumps(result, ensure_ascii=False, indent=2))
    
    sys.exit(0 if result.get("success") else 1)


if __name__ == '__main__':
    main()

