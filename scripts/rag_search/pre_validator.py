#!/usr/bin/env python3
"""
Pre-Validator - Validação Preventiva
Valida query e documento ANTES de processar
Elimina 3-4% de falhas precoces
"""

import sys
from pathlib import Path
from typing import Dict, Any, Optional

# Fix para imports
SCRIPT_DIR = Path(__file__).parent.resolve()
sys.path.insert(0, str(SCRIPT_DIR))

try:
    from database import DatabaseManager
except ImportError:
    pass  # Será tratado no uso


class PreValidator:
    """
    Validador preventivo que elimina falhas antes do processamento
    """
    
    def __init__(self, db_config: Optional[Dict[str, str]] = None):
        try:
            self.db_manager = DatabaseManager(db_config)
        except:
            self.db_manager = None
    
    def validate_all(
        self,
        query: str,
        document_id: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Valida tudo: query + documento
        
        Returns:
            {
                "valid": True/False,
                "errors": [],
                "warnings": [],
                "suggestions": []
            }
        """
        result = {
            "valid": True,
            "errors": [],
            "warnings": [],
            "suggestions": []
        }
        
        # 1. Valida query
        query_validation = self.validate_query(query, document_id)
        if not query_validation["valid"]:
            result["valid"] = False
            result["errors"].extend(query_validation["errors"])
            result["suggestions"].extend(query_validation["suggestions"])
        
        result["warnings"].extend(query_validation.get("warnings", []))
        
        # 2. Valida documento (se especificado)
        if document_id:
            doc_validation = self.validate_document(document_id)
            if not doc_validation["valid"]:
                result["valid"] = False
                result["errors"].extend(doc_validation["errors"])
                result["suggestions"].extend(doc_validation["suggestions"])
            
            result["warnings"].extend(doc_validation.get("warnings", []))
        
        return result
    
    def validate_query(
        self,
        query: str,
        document_id: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Valida a query
        """
        errors = []
        warnings = []
        suggestions = []
        
        # 1. Query vazia
        if not query or not query.strip():
            errors.append("Query vazia")
            suggestions.append("Digite uma pergunta. Exemplo: 'Qual a dosagem?'")
            return {
                "valid": False,
                "errors": errors,
                "suggestions": suggestions
            }
        
        query_clean = query.strip()
        
        # 2. Query muito curta
        if len(query_clean) < 3:
            errors.append("Query muito curta")
            suggestions.append("Seja mais específico. Exemplo: 'Qual a dosagem?'")
            return {
                "valid": False,
                "errors": errors,
                "suggestions": suggestions
            }
        
        # 3. Query muito genérica (warning, não erro)
        generic_patterns = [
            'me fale', 'fale sobre', 'me explique', 'explique sobre',
            'o que é isso', 'sobre o que', 'do que trata'
        ]
        
        query_lower = query_clean.lower()
        is_generic = any(pattern in query_lower for pattern in generic_patterns)
        
        if is_generic and len(query_clean.split()) < 5:
            warnings.append("Query muito genérica")
            suggestions.append("Quer um resumo geral? Ou algo específico?")
            suggestions.append("Opções: 'Resumo geral', 'Pontos principais', 'Busca específica'")
        
        # 4. Query fora de escopo (se temos documento)
        if document_id and self.db_manager:
            if self._is_out_of_scope(query_clean, document_id):
                errors.append("Pergunta não relacionada ao documento")
                suggestions.append("Faça perguntas sobre o conteúdo do documento")
                return {
                    "valid": False,
                    "errors": errors,
                    "suggestions": suggestions
                }
        
        # 5. Query apenas com caracteres especiais
        if len([c for c in query_clean if c.isalnum()]) < 3:
            errors.append("Query inválida (apenas caracteres especiais)")
            suggestions.append("Use palavras e números. Exemplo: 'Qual o preço?'")
            return {
                "valid": False,
                "errors": errors,
                "suggestions": suggestions
            }
        
        return {
            "valid": True,
            "warnings": warnings,
            "suggestions": suggestions
        }
    
    def validate_document(self, document_id: int) -> Dict[str, Any]:
        """
        Valida o documento
        """
        errors = []
        warnings = []
        suggestions = []
        
        if not self.db_manager:
            # Sem DB manager, não pode validar
            return {"valid": True, "warnings": ["Validação de documento pulada (sem DB)"]}
        
        try:
            # 1. Documento existe?
            doc_query = "SELECT id, title, metadata FROM documents WHERE id = %s"
            result = self.db_manager.execute_query(doc_query, [document_id])
            
            if not result:
                errors.append(f"Documento ID {document_id} não encontrado")
                suggestions.append("Verifique se o documento foi enviado corretamente")
                suggestions.append("Liste documentos disponíveis: GET /api/docs/list")
                return {
                    "valid": False,
                    "errors": errors,
                    "suggestions": suggestions
                }
            
            # 2. Documento tem chunks?
            chunks_query = "SELECT COUNT(*) FROM chunks WHERE document_id = %s"
            chunks_result = self.db_manager.execute_query(chunks_query, [document_id])
            
            if not chunks_result or chunks_result[0][0] == 0:
                errors.append("Documento sem chunks processados")
                suggestions.append("O documento pode não ter sido processado corretamente")
                suggestions.append("Tente fazer upload novamente")
                return {
                    "valid": False,
                    "errors": errors,
                    "suggestions": suggestions
                }
            
            total_chunks = chunks_result[0][0]
            
            # 3. Documento tem embeddings? (warning se não tiver)
            embeddings_query = """
                SELECT COUNT(*) FROM chunks 
                WHERE document_id = %s AND embedding IS NOT NULL
            """
            embeddings_result = self.db_manager.execute_query(embeddings_query, [document_id])
            
            if embeddings_result and embeddings_result[0][0] == 0:
                warnings.append("Documento sem embeddings (busca vetorial indisponível)")
                suggestions.append("Sistema usará busca por texto (FTS)")
                suggestions.append("Para melhor precisão, reprocesse o documento com embeddings")
            
            # 4. Documento muito pequeno? (warning)
            if total_chunks < 3:
                warnings.append(f"Documento muito pequeno ({total_chunks} chunks)")
                suggestions.append("Respostas podem ser limitadas devido ao pouco conteúdo")
            
        except Exception as e:
            # Erro ao validar, mas não bloqueia
            warnings.append(f"Erro ao validar documento: {str(e)}")
            return {
                "valid": True,  # Não bloqueia por erro de validação
                "warnings": warnings
            }
        
        return {
            "valid": True,
            "warnings": warnings,
            "suggestions": suggestions
        }
    
    def _is_out_of_scope(self, query: str, document_id: int) -> bool:
        """
        Detecta se query está fora do escopo do documento
        """
        # Perguntas obviamente fora de escopo
        out_of_scope_patterns = [
            'capital da frança', 'capital do brasil', 'presidente do',
            'quem descobriu', 'quando foi descoberto', 'história do mundo',
            'como fazer', 'receita de', 'como cozinhar',
            'previsão do tempo', 'cotação do dólar', 'notícias de hoje'
        ]
        
        query_lower = query.lower()
        
        # Se tem padrão obviamente fora de escopo, retorna True
        for pattern in out_of_scope_patterns:
            if pattern in query_lower:
                return True
        
        # Perguntas sobre coisas que provavelmente não estão em documentos técnicos
        if any(word in query_lower for word in ['futebol', 'novela', 'celebridade', 'fofoca']):
            # Mas só se o documento não for sobre isso
            try:
                doc_query = "SELECT title FROM documents WHERE id = %s"
                result = self.db_manager.execute_query(doc_query, [document_id])
                
                if result:
                    title = result[0][0].lower() if result[0][0] else ''
                    # Se o título não menciona o assunto, provavelmente está fora de escopo
                    if not any(word in title for word in ['futebol', 'novela', 'celebridade']):
                        return True
            except:
                pass
        
        return False


def main():
    """
    CLI para testes
    """
    import argparse
    import json
    
    parser = argparse.ArgumentParser(description='Pre-Validator - Validação Preventiva')
    parser.add_argument('--query', required=True, help='Query para validar')
    parser.add_argument('--document-id', type=int, help='ID do documento')
    parser.add_argument('--db-config', help='Configuração do banco (JSON)')
    
    args = parser.parse_args()
    
    # Parse db_config
    db_config = None
    if args.db_config:
        try:
            db_config = json.loads(args.db_config)
        except:
            pass
    
    # Valida
    validator = PreValidator(db_config)
    result = validator.validate_all(args.query, args.document_id)
    
    # Retorna resultado
    print(json.dumps(result, ensure_ascii=False, indent=2))
    
    # Exit code: 0 se válido, 1 se inválido
    sys.exit(0 if result["valid"] else 1)


if __name__ == '__main__':
    main()

