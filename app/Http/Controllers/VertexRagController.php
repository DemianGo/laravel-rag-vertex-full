<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\VertexClient;
use Throwable;

class VertexRagController extends Controller
{
    // GET /vertex/generate?q=...
    public function generateGet(Request $req, VertexClient $vertex)
    {
        $q = trim((string)$req->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => false, 'error' => 'Missing query param q'], 422);
        }

        try {
            $text = $vertex->generate($q, []);
            return response()->json(['ok' => true, 'prompt' => $q, 'text' => $text]);
        } catch (Throwable $e) {
            Log::error('vertex.generateGet failed: '.$e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // POST /vertex/generate  body: { "prompt": "...", "contextParts": ["...","..."]? }
    public function generatePost(Request $req, VertexClient $vertex)
    {
        $data = $req->validate([
            'prompt'       => 'required|string|min:1',
            'contextParts' => 'sometimes|array',
        ]);

        $prompt = trim($data['prompt']);
        $ctx    = $data['contextParts'] ?? [];

        try {
            $text = $vertex->generate($prompt, $ctx);
            return response()->json(['ok' => true, 'prompt' => $prompt, 'text' => $text]);
        } catch (Throwable $e) {
            Log::error('vertex.generatePost failed: '.$e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
