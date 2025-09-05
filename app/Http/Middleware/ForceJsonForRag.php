<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonForRag
{
    public function handle(Request $request, Closure $next)
    {
        // Preferimos JSON sempre nessas rotas
        $request->headers->set('Accept', 'application/json');

        // Captura qualquer saída "perdida" (warnings, echoes, HTML)
        ob_start();

        /** @var Response $response */
        $response = $next($request);

        $buffer  = ob_get_clean() ?: '';
        $status  = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
        $content = (string) (method_exists($response, 'getContent') ? $response->getContent() : '');

        // Já é JSON válido?
        $decoded = null;
        $isJson  = $this->looksLikeJson($content) && ($decoded = json_decode($content, true)) !== null && json_last_error() === JSON_ERROR_NONE;

        if ($isJson) {
            // Se havia "lixo" no buffer, anexa para diagnóstico
            if ($buffer !== '') {
                if (is_array($decoded)) {
                    $decoded['_extra_output'] = mb_substr($buffer, 0, 5000);
                    return response()->json($decoded, $status);
                }
            }
            // Garante content-type application/json
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        // NÃO JSON → embrulha em JSON padronizado
        $raw = $buffer . $content;
        return response()->json([
            'ok'     => false,
            'error'  => 'Non-JSON response intercepted',
            'status' => $status,
            'raw'    => mb_substr($raw, 0, 20000),
        ], $status);
    }

    private function looksLikeJson(string $s): bool
    {
        $s = ltrim($s);
        return $s !== '' && ($s[0] === '{' || $s[0] === '[');
    }
}
