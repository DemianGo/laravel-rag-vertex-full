<?php

use App\Modules\VideoProcessing\Controllers\VideoProcessingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Video Processing Module Routes
|--------------------------------------------------------------------------
|
| These routes are only loaded if the module is enabled in configuration.
| All routes are prefixed with /api/module/videos for consistency.
|
*/

Route::prefix('api/module/videos')->group(function () {
    // Process video endpoint
    Route::post('/process', [VideoProcessingController::class, 'process'])
        ->name('video-processing.process');
    
    // Get job status
    Route::get('/status/{job_id}', [VideoProcessingController::class, 'status'])
        ->name('video-processing.status');
    
    // List jobs for tenant
    Route::get('/list', [VideoProcessingController::class, 'list'])
        ->name('video-processing.list');
    
    // Get quota information
    Route::get('/quota', [VideoProcessingController::class, 'quota'])
        ->name('video-processing.quota');
});

