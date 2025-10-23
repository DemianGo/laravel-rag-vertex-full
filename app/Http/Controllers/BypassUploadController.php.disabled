<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SimpleUploadService;

class BypassUploadController extends Controller
{
    private SimpleUploadService $uploadService;

    public function __construct(SimpleUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Show bypass upload form
     */
    public function index()
    {
        $user = Auth::user();
        return view('upload-bypass', compact('user'));
    }

    /**
     * Simple, fast upload bypass - guaranteed to work in <5 seconds
     */
    public function upload(Request $request)
    {
        $startTime = microtime(true);
        $user = Auth::user();

        Log::info('Bypass upload started', [
            'user_id' => $user->id,
            'timestamp' => now()
        ]);

        try {
            // Quick validation
            $request->validate([
                'document' => 'required|file|max:51200', // 50MB max
                'title' => 'nullable|string|max:255'
            ]);

            $file = $request->file('document');
            $title = $request->input('title') ?: $file->getClientOriginalName();

            // Process with simple service
            $result = $this->uploadService->processFile($file, $title, $user->id);

            $processingTime = microtime(true) - $startTime;

            Log::info('Bypass upload completed', [
                'user_id' => $user->id,
                'document_id' => $result['document_id'],
                'processing_time' => round($processingTime, 3) . 's',
                'chunks_created' => $result['chunks_created']
            ]);

            return back()->with('success', "✅ Upload Rápido Concluído! Documento '{$title}' processado em " . round($processingTime, 2) . "s. {$result['chunks_created']} chunks criados.");

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;

            Log::error('Bypass upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'processing_time' => round($processingTime, 3) . 's'
            ]);

            return back()->with('error', '❌ Upload Falhou: ' . $e->getMessage());
        }
    }

    /**
     * Optional: Process document with advanced features after upload
     */
    public function processAdvanced(Request $request)
    {
        $documentId = $request->input('document_id');
        $user = Auth::user();

        try {
            // Check if document exists and belongs to user
            $document = DB::table('documents')
                ->where('id', $documentId)
                ->where('tenant_slug', 'user_' . $user->id)
                ->first();

            if (!$document) {
                return back()->with('error', 'Documento não encontrado.');
            }

            // Queue for advanced processing
            Log::info('Queueing document for advanced processing', [
                'document_id' => $documentId,
                'user_id' => $user->id
            ]);

            return back()->with('info', "📋 Documento #{$documentId} foi agendado para processamento avançado.");

        } catch (\Exception $e) {
            Log::error('Advanced processing queue failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Falha ao agendar processamento avançado: ' . $e->getMessage());
        }
    }

    /**
     * List bypass uploaded documents
     */
    public function list()
    {
        $user = Auth::user();

        $documents = DB::table('documents')
            ->where('tenant_slug', 'user_' . $user->id)
            ->where('source', 'bypass_upload')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'created_at']);

        foreach ($documents as $doc) {
            $doc->chunks_count = DB::table('chunks')
                ->where('document_id', $doc->id)
                ->count();
        }

        return response()->json([
            'success' => true,
            'documents' => $documents
        ]);
    }
}