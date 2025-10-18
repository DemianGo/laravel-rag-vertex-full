<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $documents = $this->getDocuments($user);
        
        // Support tabs: ingest, python-rag, metrics (default: ingest)
        $tab = $request->get('tab', 'ingest');

        return view('documents.index', compact('user', 'documents', 'tab'));
    }

    public function upload(Request $request)
    {
        $user = Auth::user();

        // Check document limit
        if ($user->documents_used >= $user->documents_limit) {
            return back()->with('error', 'Document limit reached. Upgrade your plan to add more documents.');
        }

        $request->validate([
            'document' => 'required|file|max:10240', // 10MB max
            'title' => 'nullable|string|max:255'
        ]);

        try {
            // Call existing RAG ingest API with timeout
            $response = Http::timeout(120)->attach(
                'file',
                file_get_contents($request->file('document')->getRealPath()),
                $request->file('document')->getClientOriginalName()
            )->post('http://127.0.0.1:8000/api/rag/ingest', [
                'tenant_slug' => 'user_' . $user->id,
                'title' => $request->title ?? $request->file('document')->getClientOriginalName()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Update user usage
                $user->increment('documents_used');
                $user->increment('tokens_used', 1); // Basic token usage for upload

                return back()->with('success', "Document uploaded successfully! {$data['chunks_created']} chunks created.");
            } else {
                $error = $response->json()['error'] ?? 'Upload failed';
                return back()->with('error', $error);
            }

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Upload failed. Please try again.');
        }
    }

    public function show($id)
    {
        $user = Auth::user();

        // Buscar documento - admin pode ver qualquer documento, usuário comum só os seus
        $query = DB::table('documents')->where('id', $id);
        
        if (!$user->is_admin) {
            $query->where('tenant_slug', 'user_' . $user->id);
        }
        
        $document = $query->first();

        if (!$document) {
            return redirect()->route('documents.index')
                ->with('error', 'Document not found');
        }

        // Buscar chunks reais do banco
        $chunks = DB::table('chunks')
            ->where('document_id', $id)
            ->orderBy('ord')
            ->get();

        return view('documents.show', compact('user', 'document', 'chunks'));
    }

    public function download($id)
    {
        $user = Auth::user();

        // Buscar documento - admin pode ver qualquer documento, usuário comum só os seus
        $query = DB::table('documents')->where('id', $id);
        
        if (!$user->is_admin) {
            $query->where('tenant_slug', 'user_' . $user->id);
        }
        
        $document = $query->first();

        if (!$document) {
            return redirect()->route('documents.index')
                ->with('error', 'Document not found');
        }

        // Tentar encontrar arquivo original
        $metadata = json_decode($document->metadata, true);
        $filePath = $metadata['file_path'] ?? null;

        if ($filePath && Storage::exists($filePath)) {
            // Arquivo encontrado - fazer download
            return Storage::download($filePath, $document->title);
        }

        // Tentar encontrar arquivo por padrão de nome
        $uploadsPath = 'uploads';
        $files = Storage::files($uploadsPath);
        
        foreach ($files as $file) {
            $fileName = basename($file);
            // Verificar se o arquivo corresponde ao documento (por timestamp ou nome)
            if (strpos($fileName, $document->title) !== false || 
                strpos($document->title, pathinfo($fileName, PATHINFO_FILENAME)) !== false) {
                return Storage::download($file, $document->title);
            }
        }

        // Arquivo não encontrado
        return redirect()->route('documents.show', $id)
            ->with('error', 'Arquivo original não encontrado. Apenas o conteúdo extraído está disponível.');
    }

    private function getDocuments($user): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/docs/list', [
                'tenant_slug' => 'user_' . $user->id
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['docs'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch documents', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function getDocumentPreview($id): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/rag/preview', [
                'document_id' => $id,
                'limit' => 5
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch document preview', ['error' => $e->getMessage()]);
        }

        return ['ok' => false, 'samples' => []];
    }
}