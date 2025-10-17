<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\BypassUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Redireciona usuários autenticados para /rag-frontend
    if (auth()->check()) {
        return redirect('/rag-frontend');
    }
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    // RAG Frontend (página principal - PROTEGIDA)
    Route::match(['get', 'head'], '/rag-frontend', function () {
        $htmlPath = resource_path('views/rag-frontend-static/index.html.protected');
        
        if (file_exists($htmlPath)) {
            $content = file_get_contents($htmlPath);
            
            // Injeta o CSRF token no HTML
            $csrfToken = csrf_token();
            $content = str_replace(
                '<meta name="csrf-token" content="">',
                '<meta name="csrf-token" content="' . $csrfToken . '">',
                $content
            );
            
            // Também injeta no campo hidden do form de logout
            $content = str_replace(
                '<input type="hidden" name="_token" id="csrfToken" value="">',
                '<input type="hidden" name="_token" id="csrfToken" value="' . $csrfToken . '">',
                $content
            );
            
            return response($content)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }
        
        abort(404, 'RAG Frontend não encontrado');
    })->name('rag-frontend');
    
    // User info API (JSON endpoint for authenticated users)
    Route::get('/api/user/info', function () {
        return response()->json([
            'user' => [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'plan' => auth()->user()->plan,
                'tokens_used' => auth()->user()->tokens_used,
                'tokens_limit' => auth()->user()->tokens_limit,
            ],
            'csrf_token' => csrf_token()
        ]);
    });
    
    // RAG API routes are in api.php with auth:sanctum middleware

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/query', [ChatController::class, 'query'])->name('chat.query');

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('documents.show');

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

    // Bypass Upload System (fast alternative)
    Route::get('/upload-bypass', [BypassUploadController::class, 'index'])->name('upload-bypass.index');
    Route::post('/upload-bypass', [BypassUploadController::class, 'upload'])->name('upload-bypass.upload');
    Route::post('/upload-bypass/process-advanced', [BypassUploadController::class, 'processAdvanced'])->name('upload-bypass.process-advanced');
    Route::get('/api/bypass-documents', [BypassUploadController::class, 'list'])->name('upload-bypass.list');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
