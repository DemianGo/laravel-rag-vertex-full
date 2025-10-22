<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->unique();
            $table->string('tenant_slug');
            $table->string('video_id', 20);
            $table->string('video_url');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            
            // Metadados YouTube
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('channel_name')->nullable();
            $table->timestamp('published_at')->nullable();
            
            // Storage paths
            $table->string('audio_path')->nullable();
            $table->string('transcription_path')->nullable();
            
            // Signed URLs
            $table->text('audio_url')->nullable();
            $table->text('transcription_url')->nullable();
            $table->timestamp('urls_expire_at')->nullable();
            
            // Tracking
            $table->text('error_message')->nullable();
            $table->json('processing_log')->default('[]');
            $table->integer('retry_count')->default(0);
            $table->unsignedBigInteger('rag_document_id')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_slug', 'status']);
            $table->index(['tenant_slug', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_processing_jobs');
    }
};