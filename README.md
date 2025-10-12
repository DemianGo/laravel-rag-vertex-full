<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# 🚀 Laravel RAG System with Vertex AI

Enterprise-grade RAG (Retrieval-Augmented Generation) system built with Laravel 11, Python, and Google Vertex AI.

## ✨ Features

### 📄 Document Support (23+ formats)
- **Documents:** PDF, DOCX, XLSX, PPTX, TXT, CSV, HTML, XML, RTF
- **Images:** PNG, JPG, GIF, BMP, TIFF, WebP (with OCR)
- **Videos:** YouTube, Vimeo, TikTok, 1000+ sites (with transcription)
- **Advanced:** PDF with tables, Excel aggregations, PPTX slides, OCR on scanned PDFs

### 🎯 RAG Capabilities
- **Smart Router:** Auto-detects best search strategy
- **Multiple Modes:** direct, summary, quote, list, table, document_full
- **Hybrid Search:** Vector + FTS (Full-Text Search)
- **Intelligent Caching:** 3-level cache system
- **Question Suggestions:** Auto-generated based on document type
- **Feedback System:** User ratings (👍👎) with analytics

### 🎬 Video Processing (NEW)
- Upload local videos or paste URLs
- Supports 1000+ video platforms
- Automatic transcription (Gemini/Google/OpenAI)
- Multi-language support

### 🔍 OCR Support (NEW)
- Scanned PDFs (100% images)
- Images within PDFs
- Standalone images
- Tesseract OCR engine

## 🚀 Quick Start

```bash
# Install dependencies
composer install
pip3 install -r scripts/document_extraction/requirements.txt
pip3 install -r scripts/rag_search/requirements.txt
pip3 install -r scripts/video_processing/requirements.txt

# Configure .env
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start server
php artisan serve
```

## 📊 System Coverage

| Format | Coverage | Features |
|--------|----------|----------|
| PDF | 99% | Text + Tables + Images OCR |
| Excel | 90% | Structured queries + Aggregations |
| CSV | 90% | Intelligent chunking |
| PPTX | 90% | Slides + Notes + Tables |
| DOCX | 95% | Text + Tables |
| HTML | 85% | Text + Tables |
| Images | 85% | OCR (Tesseract) |
| Videos | 90% | Transcription + Indexing |

**Overall Coverage: 92%** across 23+ formats

## 📚 Documentation

See [PROJECT_README.md](PROJECT_README.md) for detailed documentation.

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
