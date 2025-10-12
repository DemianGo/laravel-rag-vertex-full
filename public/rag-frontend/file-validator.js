/**
 * File Validator & Info Display
 * Validates files before upload and displays size/page information
 * Independent module - doesn't modify existing files
 */

(function() {
    'use strict';

    // Constants
    const MAX_FILE_SIZE = 500 * 1024 * 1024; // 500MB
    const MAX_PAGES = 5000;
    const MAX_FILES = 5;
    
    // Valid document extensions
    const VALID_EXTENSIONS = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'txt', 'csv', 'html', 'xml', 'rtf'];

    // Page estimation rules
    const PAGE_ESTIMATION = {
        'pdf': { bytesPerPage: 100000, exact: false }, // ~100KB per page
        'docx': { bytesPerPage: 5000, exact: false },  // ~5KB per page
        'doc': { bytesPerPage: 5000, exact: false },
        'xlsx': { bytesPerPage: 10240, exact: false }, // ~10KB per page (50 rows = 1 page)
        'xls': { bytesPerPage: 10240, exact: false },
        'pptx': { bytesPerPage: 200000, exact: false }, // ~200KB per slide
        'ppt': { bytesPerPage: 200000, exact: false },
        'txt': { bytesPerPage: 4096, exact: false },   // 4KB per page
        'csv': { bytesPerPage: 4096, exact: false },
        'html': { bytesPerPage: 4096, exact: false },
        'xml': { bytesPerPage: 4096, exact: false },
        'rtf': { bytesPerPage: 3072, exact: false },
        'default': { bytesPerPage: 4096, exact: false }
    };

    // Estimate pages from file
    function estimatePages(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const rule = PAGE_ESTIMATION[ext] || PAGE_ESTIMATION['default'];
        const estimatedPages = Math.ceil(file.size / rule.bytesPerPage);
        
        return {
            pages: estimatedPages,
            exact: rule.exact
        };
    }

    // Format bytes to human readable
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
    }

    // Check if file extension is valid
    function isValidDocumentType(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        return VALID_EXTENSIONS.includes(ext);
    }

    // Validate single file
    function validateFile(file) {
        const errors = [];
        const warnings = [];
        const info = {
            name: file.name,
            size: file.size,
            sizeFormatted: formatBytes(file.size),
            type: file.type,
            extension: file.name.split('.').pop().toLowerCase()
        };

        // Check if it's a valid document type
        if (!isValidDocumentType(file)) {
            errors.push(`Tipo de arquivo não suportado (.${info.extension}). Formatos aceitos: ${VALID_EXTENSIONS.join(', ')}`);
            return {
                valid: false,
                errors: errors,
                warnings: warnings,
                info: info
            };
        }

        // Estimate pages
        const pageInfo = estimatePages(file);
        info.estimatedPages = pageInfo.pages;
        info.pagesExact = pageInfo.exact;

        // Validate size
        if (file.size > MAX_FILE_SIZE) {
            errors.push(`Arquivo excede ${formatBytes(MAX_FILE_SIZE)} (máximo permitido)`);
        }

        // Validate pages
        if (info.estimatedPages > MAX_PAGES) {
            errors.push(`Estimativa de ${info.estimatedPages} páginas excede limite de ${MAX_PAGES} páginas`);
        }

        // Warnings
        if (file.size > 100 * 1024 * 1024) { // > 100MB
            warnings.push('Arquivo grande, processamento pode levar 2-3 minutos');
        }

        if (info.estimatedPages > 1000) {
            warnings.push(`~${info.estimatedPages} páginas, processamento pode demorar`);
        }

        return {
            valid: errors.length === 0,
            errors,
            warnings,
            info
        };
    }

    // Validate multiple files
    function validateFiles(files) {
        // Filter only valid document types
        const validFiles = Array.from(files).filter(isValidDocumentType);
        const invalidFiles = Array.from(files).filter(file => !isValidDocumentType(file));
        
        // Show error for invalid file types
        if (invalidFiles.length > 0) {
            const invalidExtensions = [...new Set(invalidFiles.map(f => f.name.split('.').pop().toLowerCase()))];
            return {
                valid: false,
                errors: [`Arquivos não suportados: ${invalidExtensions.join(', ')}. Formatos aceitos: ${VALID_EXTENSIONS.join(', ')}`],
                files: []
            };
        }

        if (validFiles.length > MAX_FILES) {
            return {
                valid: false,
                errors: [`Máximo de ${MAX_FILES} arquivos simultâneos. Enviados: ${validFiles.length}`],
                files: []
            };
        }

        const results = validFiles.map(validateFile);
        const allValid = results.every(r => r.valid);

        return {
            valid: allValid,
            errors: results.flatMap(r => r.errors),
            files: results
        };
    }

    // Create info badge HTML
    function createFileInfoBadge(validation) {
        const { info, errors, warnings } = validation;
        
        let statusClass = 'success';
        let statusIcon = '✓';
        
        if (errors.length > 0) {
            statusClass = 'danger';
            statusIcon = '✗';
        } else if (warnings.length > 0) {
            statusClass = 'warning';
            statusIcon = '⚠';
        }

        const pagesText = info.pagesExact ? 
            `${info.estimatedPages} páginas` : 
            `~${info.estimatedPages} páginas (estimado)`;

        let html = `
            <div class="file-info-badge alert alert-${statusClass} alert-dismissible fade show p-2 mb-2" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-${statusClass}">${statusIcon}</span>
                    <div class="flex-grow-1 small">
                        <strong>${info.name}</strong>
                        <div class="text-muted">
                            ${info.sizeFormatted} • ${pagesText} • ${info.extension.toUpperCase()}
                        </div>
        `;

        if (errors.length > 0) {
            html += `<div class="text-danger mt-1">❌ ${errors.join(', ')}</div>`;
        }

        if (warnings.length > 0) {
            html += `<div class="text-warning mt-1">⚠️ ${warnings.join(', ')}</div>`;
        }

        html += `
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        `;

        return html;
    }

    // Display validation results
    function displayValidationResults(files, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const validation = validateFiles(files);
        
        let html = '';
        
        if (validation.files.length > 0) {
            validation.files.forEach(fileValidation => {
                html += createFileInfoBadge(fileValidation);
            });
        }

        if (validation.errors.length > 0 && validation.files.length === 0) {
            html += `
                <div class="alert alert-danger alert-dismissible fade show p-2 mb-2" role="alert">
                    <strong>Erro:</strong> ${validation.errors.join(', ')}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }

        container.innerHTML = html;
        
        return validation;
    }

    // Export to global
    window.FileValidator = {
        validateFile,
        validateFiles,
        displayValidationResults,
        createFileInfoBadge,
        formatBytes,
        estimatePages,
        MAX_FILE_SIZE,
        MAX_PAGES,
        MAX_FILES
    };

    console.log('✅ FileValidator loaded - Max: 5000 pages, 500MB, 5 files');
})();

