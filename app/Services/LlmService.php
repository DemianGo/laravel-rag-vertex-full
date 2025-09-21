<?php

namespace App\Services;

class LlmService
{
    /** Retorna true se o LLM estiver habilitado por chave/config. */
    public function enabled(): bool
    {
        $provider = env('LLM_PROVIDER', 'googleai');
        $key = env('GOOGLE_GENAI_API_KEY') ?: env('GOOGLE_API_KEY');
        return ($provider === 'googleai') && !empty($key);
    }

    /**
     * Reescreve uma única linha (item da lista) mantendo o conteúdo factual.
     * - $format: plain|markdown|html (apenas influencia pontuação/estilo leve)
     */
    public function rewriteLine(string $line, string $format = 'plain'): ?string
    {
        $directive = match ($format) {
            'html'     => "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha em HTML simples (sem <p> de abertura/fecho extra, sem lista).",
            'markdown' => "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha em Markdown simples (sem prefixos numéricos).",
            default    => "Reescreva a frase abaixo, mantendo o sentido factual. Responda com uma ÚNICA linha de texto.",
        };

        $prompt = $directive . "\n\nFrase:\n" . $line . "\n\nApenas a linha reescrita:";
        $out = $this->answerFromContext($prompt, '', $line);
        if (!is_string($out)) return null;

        // Normaliza saída para 1 linha
        $out = trim(preg_replace('/\s+/u', ' ', $out));
        return $out !== '' ? $out : null;
    }

    /**
     * Gera uma resposta com base em contexto + seed (genérico).
     * Aqui reutilizamos o mesmo caminho já configurado para Google AI (Gemini).
     */
    public function answerFromContext(string $prompt, string $context, string $seed = ''): ?string
    {
        // Implementação simples baseada em env; sem dependências externas novas.
        $provider = env('LLM_PROVIDER', 'googleai');
        if ($provider !== 'googleai') return null;

        $apiKey = env('GOOGLE_GENAI_API_KEY') ?: env('GOOGLE_API_KEY');
        if (!$apiKey) return null;

        // Monta um "conteúdo" concatenado: prompt + contexto + seed
        $content = trim($prompt . "\n\n" .
                        ($context !== '' ? "=== CONTEXTO ===\n{$context}\n\n" : '') .
                        ($seed !== ''    ? "=== TEXTO BASE ===\n{$seed}\n\n" : '') .
                        "=== RESPOSTA ===\n");

        // Chamada via cURL puro (evita adicionar libs); modelo default por ENV
        $model = env('GOOGLE_GENAI_MODEL', 'gemini-1.5-flash');
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $payload = [
            "contents" => [[
                "parts" => [[ "text" => $content ]]
            ]],
            "generationConfig" => [
                "temperature" => 0.2,
                "topK" => 40,
                "topP" => 0.95,
                // tokens altos para evitar corte acidental de listas
                "maxOutputTokens" => 2048
            ],
            "safetySettings" => [
                // Mantém padrão seguro; pode ajustar via ENV no futuro
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err) return null;

        $json = json_decode($resp, true);
        if (!is_array($json)) return null;

        // Extrai o primeiro texto
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text)) return null;

        return trim($text);
    }
}
