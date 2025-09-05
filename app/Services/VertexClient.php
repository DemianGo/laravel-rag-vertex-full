<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Google\Auth\ApplicationDefaultCredentials;
use Throwable;

class VertexClient
{
    private string $project;
    private string $location;
    private string $embeddingModel;
    private string $generationModel;

    public function __construct()
    {
        $this->project         = config('services.vertex.project') ?: (env('GOOGLE_CLOUD_PROJECT') ?: env('VERTEX_PROJECT'));
        $this->location        = config('services.vertex.location', 'us-central1');
        $this->embeddingModel  = config('services.vertex.embedding_model', 'text-embedding-004');
        $this->generationModel = config('services.vertex.generation_model', 'gemini-2.5-flash');
    }

    /** @return array<int, array<float>> */
    public function embed(array $texts): array
    {
        if (empty($texts)) return [];

        $instances = array_map(fn($t) => ['content' => (string)$t], $texts);
        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:predict',
            $this->location, $this->project, $this->location, $this->embeddingModel
        );

        $res = Http::withToken($this->getAccessToken())
            ->timeout(60)
            ->asJson()
            ->post($url, [
                'instances'  => $instances,
                'parameters' => ['autoTruncate' => true],
            ])->json();

        $out = [];
        foreach (($res['predictions'] ?? []) as $p) {
            if (isset($p['embeddings']['values'])) {
                $out[] = $p['embeddings']['values'];
            } elseif (isset($p['values'])) {
                $out[] = $p['values'];
            }
        }
        return $out;
    }

    public function generate(string $prompt, array $contextParts = []): string
    {
        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $this->location, $this->project, $this->location, $this->generationModel
        );

        $parts = [];
        foreach ($contextParts as $c) {
            $parts[] = ['text' => (string)$c];
        }
        $parts[] = ['text' => $prompt];

        $res = Http::withToken($this->getAccessToken())
            ->timeout(60)
            ->asJson()
            ->post($url, [
                'contents' => [[ 'role' => 'user', 'parts' => $parts ]],
                'safetySettings' => [],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 32,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ],
            ])->json();

        $text = '';
        if (!empty($res['candidates'][0]['content']['parts'])) {
            foreach ($res['candidates'][0]['content']['parts'] as $p) {
                if (isset($p['text'])) { $text .= $p['text']; }
            }
        }
        return $text ?: '';
    }

    private function getAccessToken(): string
    {
        // 1) Token via env
        $envTok = getenv('VERTEX_ACCESS_TOKEN');
        if ($envTok) return trim($envTok);

        // 2) gcloud (ADC)
        try {
            $tok = @shell_exec('gcloud auth print-access-token 2>/dev/null');
            if ($tok) return trim($tok);
        } catch (Throwable $e) {}

        // 3) google/auth (ADC via biblioteca)
        if (class_exists(ApplicationDefaultCredentials::class)) {
            $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
            $creds = ApplicationDefaultCredentials::getCredentials($scopes);
            $token = $creds->fetchAuthToken();
            if (!empty($token['access_token'])) return $token['access_token'];
        }

        throw new \RuntimeException('Sem token GCP. Rode "gcloud auth application-default login" ou defina VERTEX_ACCESS_TOKEN.');
    }
}
