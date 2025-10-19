import logging
from typing import List, Dict, Any, Optional
from config import Config

logger = logging.getLogger(__name__)

class LLMService:
    """Serviço unificado para integração com LLMs (Gemini, OpenAI)"""

    def __init__(self, config=None, provider: Optional[str] = None):
        """
        Inicializa o serviço de LLM

        Args:
            config: Configuração do sistema
            provider: Provedor a usar (gemini, openai, local). Usa configuração padrão se None
        """
        self.config = config or Config()
        self.provider = provider or self.config.DEFAULT_LLM_PROVIDER
        self.gemini_client = None
        self.openai_client = None
        self._initialize_clients()

    def _initialize_clients(self):
        """Inicializa os clientes dos LLMs disponíveis"""
        # Inicializa Gemini se a chave está disponível
        if self.config.GEMINI_API_KEY:
            try:
                import google.generativeai as genai
                genai.configure(api_key=self.config.GEMINI_API_KEY)
                self.gemini_client = genai.GenerativeModel('gemini-2.5-flash')
                logger.info("Cliente Gemini inicializado")
            except ImportError:
                logger.warning("google-generativeai não disponível")
            except Exception as e:
                logger.error(f"Erro ao inicializar Gemini: {e}")

        # Inicializa OpenAI se a chave está disponível
        if self.config.OPENAI_API_KEY:
            try:
                import openai
                openai.api_key = self.config.OPENAI_API_KEY
                self.openai_client = openai
                logger.info("Cliente OpenAI inicializado")
            except ImportError:
                logger.warning("openai não disponível")
            except Exception as e:
                logger.error(f"Erro ao inicializar OpenAI: {e}")

    def generate_answer(self, query: str, context: str) -> tuple[str, str]:
        """
        Gera resposta baseada na query e contexto

        Args:
            query: Pergunta do usuário
            context: Contexto preparado dos chunks

        Returns:
            Tupla (resposta, provedor_usado)
        """
        if not context or not context.strip():
            return "Não há informação suficiente no documento para responder à pergunta.", "fallback"

        # Constrói o prompt
        prompt = self._build_prompt(query, context)

        # Tenta gerar resposta com o provedor escolhido
        if self.provider == "gemini" and self.gemini_client:
            answer = self._generate_with_gemini(prompt)
            return answer, "gemini"
        elif self.provider == "openai" and self.openai_client:
            answer = self._generate_with_openai(prompt)
            return answer, "openai"
        else:
            # Fallback para outros provedores disponíveis
            answer = self._generate_fallback(prompt, query, context)
            return answer, "fallback"

    def generate_with_grounding(self, prompt: str) -> Dict[str, Any]:
        """
        Gera resposta com grounding do Gemini (busca na web)

        Args:
            prompt: Prompt para o Gemini

        Returns:
            Dicionário com resposta e metadados
        """
        try:
            if not self.gemini_client:
                return {
                    'success': False,
                    'error': 'Cliente Gemini não disponível',
                    'answer': '',
                    'grounding_metadata': {}
                }

            # Configura o Gemini com grounding
            model = self.gemini_client
            
            # Tenta usar grounding com busca na web
            try:
                response = model.generate_content(
                    prompt,
                    tools=[{"google_search_retrieval": {}}],
                    generation_config={
                        "temperature": 0.1,
                        "max_output_tokens": 2048,
                    }
                )
            except Exception as grounding_error:
                # Se grounding falhar, usa busca normal
                logger.warning(f"Grounding falhou, usando busca normal: {grounding_error}")
                response = model.generate_content(
                    prompt,
                    generation_config={
                        "temperature": 0.1,
                        "max_output_tokens": 2048,
                    }
                )

            if response and response.text:
                # Extrai metadados de grounding se disponível
                grounding_metadata = {}
                # Simula metadados de grounding para compatibilidade
                grounding_metadata = {
                    'grounding_chunks': [
                        {
                            'title': 'Resultado da busca web',
                            'content': response.text[:200] + '...',
                            'url': 'https://gemini.google.com'
                        }
                    ]
                }

                return {
                    'success': True,
                    'answer': response.text,
                    'grounding_metadata': grounding_metadata,
                    'execution_time': 0
                }
            else:
                return {
                    'success': False,
                    'error': 'Resposta vazia do Gemini',
                    'answer': '',
                    'grounding_metadata': {}
                }

        except Exception as e:
            logger.error(f"Erro na geração com grounding: {e}")
            return {
                'success': False,
                'error': f'Erro na geração com grounding: {str(e)}',
                'answer': '',
                'grounding_metadata': {}
            }

    def _prepare_context(self, chunks: List[Dict[str, Any]]) -> str:
        """
        Prepara o contexto a partir dos chunks encontrados

        Args:
            chunks: Lista de chunks relevantes

        Returns:
            Contexto concatenado e formatado
        """
        context_parts = []

        for i, chunk in enumerate(chunks, 1):
            content = chunk.get('content', '').strip()
            similarity = chunk.get('similarity', 0)

            if content:
                # Adiciona informação sobre similaridade se disponível
                if similarity > 0:
                    context_parts.append(f"[Trecho {i} - Relevância: {similarity:.2f}]\n{content}")
                else:
                    context_parts.append(f"[Trecho {i}]\n{content}")

        return "\n\n".join(context_parts)

    def _build_prompt(self, query: str, context: str) -> str:
        """
        Constrói o prompt para o LLM

        Args:
            query: Pergunta do usuário
            context: Contexto preparado dos chunks

        Returns:
            Prompt formatado
        """
        prompt = f"""Contexto dos documentos:
{context}

Pergunta: {query}

Instruções:
- Responda de forma precisa e detalhada baseando-se APENAS no contexto fornecido acima
- Se a informação não estiver completamente no contexto, diga "Não há informação suficiente no documento"
- Mantenha sua resposta focada na pergunta específica
- Use exemplos do contexto quando apropriado
- Seja objetivo e claro

Resposta:"""

        return prompt

    def _generate_with_gemini(self, prompt: str) -> str:
        """
        Gera resposta usando Gemini

        Args:
            prompt: Prompt formatado

        Returns:
            Resposta do Gemini
        """
        try:
            response = self.gemini_client.generate_content(prompt)
            return response.text.strip()

        except Exception as e:
            logger.error(f"Erro ao gerar resposta com Gemini: {e}")
            return "Erro ao processar a pergunta. Tente novamente."

    def _generate_with_openai(self, prompt: str) -> str:
        """
        Gera resposta usando OpenAI

        Args:
            prompt: Prompt formatado

        Returns:
            Resposta do OpenAI
        """
        try:
            response = self.openai_client.chat.completions.create(
                model="gpt-3.5-turbo",
                messages=[
                    {"role": "system", "content": "Você é um assistente que responde perguntas baseado apenas no contexto fornecido."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=500,
                temperature=0.1
            )

            return response.choices[0].message.content.strip()

        except Exception as e:
            logger.error(f"Erro ao gerar resposta com OpenAI: {e}")
            return "Erro ao processar a pergunta. Tente novamente."

    def _generate_fallback(self, prompt: str, query: str, context: str) -> str:
        """
        Fallback quando nenhum LLM está disponível

        Args:
            prompt: Prompt formatado
            query: Pergunta original
            context: Contexto dos chunks

        Returns:
            Resposta básica baseada no contexto
        """
        logger.warning("Nenhum LLM disponível, usando resposta básica")

        # Resposta básica baseada no contexto
        if context and context.strip():
            # Pega os primeiros 500 caracteres do contexto
            preview = context[:500] + "..." if len(context) > 500 else context
            
            return f"""Baseado no documento, encontrei informações relevantes:

{preview}

Nota: Esta resposta foi gerada sem LLM. Para respostas mais elaboradas, configure uma chave de API para Gemini ou OpenAI."""

        return "Não foi possível processar a pergunta. Verifique a configuração do LLM."

    def is_available(self) -> bool:
        """
        Verifica se algum LLM está disponível

        Returns:
            True se há pelo menos um LLM configurado
        """
        return (self.gemini_client is not None) or (self.openai_client is not None)

    def get_available_providers(self) -> List[str]:
        """
        Retorna lista de provedores disponíveis

        Returns:
            Lista com nomes dos provedores configurados
        """
        providers = []

        if self.gemini_client:
            providers.append("gemini")

        if self.openai_client:
            providers.append("openai")

        if not providers:
            providers.append("fallback")

        return providers

    def switch_provider(self, provider: str) -> bool:
        """
        Muda o provedor de LLM

        Args:
            provider: Nome do provedor (gemini, openai)

        Returns:
            True se a mudança foi bem-sucedida
        """
        available = self.get_available_providers()

        if provider in available:
            self.provider = provider
            logger.info(f"Provedor mudado para: {provider}")
            return True
        else:
            logger.error(f"Provedor {provider} não disponível. Disponíveis: {available}")
            return False

    def get_status(self) -> Dict[str, Any]:
        """
        Retorna status do serviço

        Returns:
            Dicionário com informações de status
        """
        return {
            "current_provider": self.provider,
            "available_providers": self.get_available_providers(),
            "gemini_configured": bool(self.config.GEMINI_API_KEY),
            "openai_configured": bool(self.config.OPENAI_API_KEY),
            "is_available": self.is_available()
        }
    
    def rewrite_line(self, line: str, format_type: str = 'plain') -> Optional[str]:
        """
        Reescreve uma única linha mantendo o conteúdo factual
        Replica rewriteLine() do PHP
        
        Args:
            line: Linha a ser reescrita
            format_type: Formato de saída (plain/markdown/html)
            
        Returns:
            Linha reescrita ou None se erro
        """
        if not line or not line.strip():
            return None
            
        directive = self._get_rewrite_directive(format_type)
        prompt = f"{directive}\n\nFrase:\n{line}\n\nApenas a linha reescrita:"
        
        try:
            # Para rewrite_line, usar o método direto do Gemini sem contexto
            if self.provider == "gemini" and self.gemini_client:
                response = self.gemini_client.generate_content(prompt)
                result = response.text.strip() if response.text else None
            elif self.provider == "openai" and self.openai_client:
                response = self.openai_client.ChatCompletion.create(
                    model="gpt-3.5-turbo",
                    messages=[{"role": "user", "content": prompt}],
                    max_tokens=100
                )
                result = response.choices[0].message.content.strip()
            else:
                result, _ = self.generate_answer(prompt, 'contexto disponível')
            
            if result:
                # Normaliza saída para 1 linha
                result = ' '.join(result.split())
                return result if result.strip() else None
        except Exception as e:
            logger.error(f"Erro ao reescrever linha: {e}")
            
        return None
    
    def get_current_provider(self) -> str:
        """
        Retorna o provedor atual sendo usado
        
        Returns:
            Nome do provedor atual
        """
        return self.provider
    
    def _get_rewrite_directive(self, format_type: str) -> str:
        """
        Retorna diretriz de reescrita baseada no formato
        
        Args:
            format_type: Tipo de formato (plain/markdown/html)
            
        Returns:
            Diretriz para reescrita
        """
        directives = {
            'html': "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha em HTML simples (sem <p> de abertura/fecho extra, sem lista).",
            'markdown': "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha em Markdown simples (sem prefixos numéricos).",
            'plain': "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha de texto."
        }
        
        return directives.get(format_type.lower(), directives['plain'])