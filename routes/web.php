<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\BypassUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
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
