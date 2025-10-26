# Laravel RAG System

> **Sistema de RAG (Retrieval-Augmented Generation) completo com suporte multi-usuário, processamento de 15 formatos de arquivo e busca inteligente.**

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.12-green.svg)](https://python.org)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-blue.svg)](https://postgresql.org)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-brightgreen.svg)](https://github.com)

## 🎯 **Visão Geral**

Sistema completo de RAG que permite aos usuários fazer upload de documentos, gerar embeddings e fazer buscas inteligentes usando IA. Suporta 15 formatos de arquivo, processamento de vídeos, OCR avançado e sistema multi-usuário com isolamento completo de dados.

## ✨ **Principais Recursos**

### 🔐 **Sistema Multi-Usuário**
- ✅ **Autenticação dual**: Web sessions + API tokens
- ✅ **Isolamento de dados**: Cada usuário vê apenas seus documentos
- ✅ **Sistema de planos**: Free, Pro, Enterprise
- ✅ **API keys individuais**: Autenticação programática

### 📄 **15 Formatos Suportados**
- ✅ **Documentos**: PDF, DOCX, XLSX, PPTX, TXT, CSV, HTML, XML, RTF
- ✅ **Imagens**: PNG, JPG, GIF, BMP, TIFF, WebP (com OCR)
- ✅ **Vídeos**: YouTube, Vimeo, 1000+ sites (com transcrição)

### 🧠 **RAG Inteligente**
- ✅ **Busca vetorial**: Embeddings 768d (all-mpnet-base-v2)
- ✅ **LLM Integration**: Gemini AI + OpenAI fallback
- ✅ **Smart Router**: Decisão automática de estratégia
- ✅ **Cache Layer**: Redis/File com hit rate tracking
- ✅ **Fallback System**: 5 níveis de recuperação

### 🎥 **Processamento de Vídeos**
- ✅ **1000+ sites**: YouTube, Vimeo, Instagram, TikTok, etc
- ✅ **Transcrição automática**: Gemini + Google Speech + Whisper
- ✅ **Limite**: 60 minutos por vídeo
- ✅ **Precisão**: 90%+

### 📊 **Analytics e Feedback**
- ✅ **Sistema de feedback**: 👍👎 após respostas
- ✅ **Métricas detalhadas**: Cache, embeddings, queries
- ✅ **Dashboard**: Estatísticas em tempo real
- ✅ **Question Suggester**: Perguntas inteligentes por tipo de documento

## 🚀 **Instalação Rápida**

### **Pré-requisitos**
- PHP 8.4+
- Laravel 12.x
- PostgreSQL 14+
- Python 3.12+
- Node.js 18+

### **1. Clone o Repositório**
```bash
git clone <repository-url>
cd laravel-rag-vertex-full
```

### **2. Instalar Dependências**
```bash
# PHP
composer install

# Python
pip install -r scripts/rag_search/requirements.txt
pip install -r scripts/document_extraction/requirements.txt
pip install -r scripts/video_processing/requirements.txt

# Node.js
npm install
```

### **3. Configurar Ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

### **4. Configurar Banco de Dados**
```bash
php artisan migrate
php artisan db:seed
```

### **5. Configurar Serviços de IA**
Edite o `.env` com suas chaves de API:
```env
GOOGLE_GENAI_API_KEY=your_gemini_key
GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json
OPENAI_API_KEY=your_openai_key
```

### **6. Iniciar Servidor**

**IMPORTANTE:** Este sistema usa arquitetura híbrida Laravel + FastAPI

#### **Laravel (porta 8000)** - Frontend e autenticação:
```bash
php artisan serve
```
**Laravel APENAS serve:**
- ✅ Views HTML (frontend)
- ✅ Login/Registro (autenticação)
- ✅ Admin Panel
- ❌ NÃO processa APIs de RAG

#### **FastAPI (porta 8002)** - Todo o sistema de RAG:
```bash
python3 simple_rag_ingest.py
```
**FastAPI processa TUDO:**
- ✅ Upload de documentos (POST /api/rag/ingest)
- ✅ Lista documentos (GET /api/docs/list)
- ✅ Busca RAG (POST /api/rag/python-search)
- ✅ Todas as operações de processamento

**⚠️ IMPORTANTE:** Não use Laravel para APIs. Todo backend é FastAPI!

## 🎯 **Uso Rápido**

### **1. Acesso ao Sistema**
- **Página Principal**: `http://localhost:8000/` (Home/Welcome)
- **RAG Console**: `http://localhost:8000/rag-frontend/` (Aplicação Principal)
- **Admin**: `http://localhost:8000/admin/` (Painel Administrativo)

### **2. Upload de Documentos**
- Suporte a 15 formatos
- Upload individual ou em lote
- Processamento automático com embeddings

### **3. Busca RAG**
- **Busca Simples**: Texto livre
- **Busca Avançada**: Com parâmetros específicos
- **Smart Mode**: Decisão automática de estratégia

### **4. Processamento de Vídeos**
- URL de qualquer site suportado
- Transcrição automática
- Busca no conteúdo transcrito

## 📚 **Documentação Completa**

Para documentação detalhada, consulte:
- **[PROJECT_README.md](./PROJECT_README.md)** - Estado atual completo do projeto
- **[API Documentation](./docs/api.md)** - Endpoints e exemplos
- **[Deployment Guide](./docs/deployment.md)** - Guia de produção

## 🔧 **Arquitetura**

### **⚠️ SEPARAÇÃO DE RESPONSABILIDADES:**

#### **1. Laravel (Porta 8000) - Frontend e Autenticação:**
```
Frontend (Laravel Blade + Bootstrap)
    ↓
Laravel 12 + PHP 8.4
    ├── Views HTML/CSS/JS (✅)
    ├── Login/Registro (✅)
    ├── Admin Panel (✅)
    └── NÃO processa APIs (❌)
```

**Laravel APENAS serve:**
- ✅ Views para HTML renderizado
- ✅ Autenticação web (sessions)
- ✅ Admin panel para gerenciamento
- ❌ **NÃO processa APIs de RAG**

#### **2. FastAPI (Porta 8002) - Todo o Backend:**
```
FastAPI (Python 3.12)
    ↓
APIs RAG + Processamento
    ├── POST /api/rag/ingest (✅)
    ├── GET /api/docs/list (✅)
    ├── POST /api/rag/python-search (✅)
    └── Todas as operações (✅)
    ↓
Python Scripts (RAG + Extraction)
    ↓
Database (PostgreSQL + Vector Search)
```

**FastAPI processa TUDO:**
- ✅ Upload de documentos
- ✅ Listagem de documentos
- ✅ Busca RAG
- ✅ Processamento de vídeos
- ✅ Geração de embeddings
- ✅ Todas as operações de backend

### **Componentes Principais**
- **Laravel Controllers**: 22 arquivos (apenas para views)
- **FastAPI Endpoints**: APIs de processamento
- **Python Scripts**: 100+ arquivos para RAG e extração
- **Middleware**: Autenticação dual, planos, API keys
- **Database**: PostgreSQL + Vector Search

## 📊 **Estatísticas do Projeto**

- **Linhas de Código**: 50,000+
- **Arquivos**: 200+
- **Endpoints API**: 48+
- **Formatos Suportados**: 15
- **Sites de Vídeo**: 1000+
- **Usuários Simultâneos**: Ilimitado
- **Documentos por Usuário**: Até 50 (Pro) / Ilimitado (Enterprise)

## 🎯 **Casos de Uso**

### **Educação**
- Upload de materiais didáticos
- Busca inteligente em conteúdo
- Transcrição de aulas em vídeo

### **Empresarial**
- Documentação técnica
- Relatórios e apresentações
- Treinamentos em vídeo

### **Pesquisa**
- Análise de documentos
- Extração de informações
- Síntese de conteúdo

## 🤝 **Contribuição**

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 **Licença**

Este projeto está licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 🆘 **Suporte**

- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)
- **Documentação**: [Wiki](https://github.com/your-repo/wiki)
- **Email**: support@yourdomain.com

## 🏆 **Roadmap**

- [ ] **v2.0**: Interface React/ Vue.js
- [ ] **v2.1**: Suporte a mais idiomas
- [ ] **v2.2**: API GraphQL
- [ ] **v2.3**: Integração com Slack/Teams
- [ ] **v2.4**: Análise de sentimento
- [ ] **v2.5**: Chat em tempo real

---

**Desenvolvido com ❤️ usando Laravel, Python e IA**

[![Made with Laravel](https://img.shields.io/badge/Made%20with-Laravel-red.svg)](https://laravel.com)
[![Powered by AI](https://img.shields.io/badge/Powered%20by-AI-blue.svg)](https://openai.com)