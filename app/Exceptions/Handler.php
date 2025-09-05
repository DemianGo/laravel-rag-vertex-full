<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        //
    }

    /**
     * Força JSON para requisições /api/* e /rag/* (inclusive erros).
     */
    public function render($request, Throwable $e)
    {
        $wantsJson =
            $request->expectsJson() ||
            $request->is('api/*') ||
            $request->is('rag/*');

        if (!$wantsJson) {
            return parent::render($request, $e);
        }

        // Validation
        if ($e instanceof ValidationException) {
            return response()->json([
                'ok'     => false,
                'error'  => 'Validação falhou.',
                'type'   => 'ValidationException',
                'errors' => $e->errors(),
            ], 422);
        }

        // Auth / autorização / 404 / método / throttle
        if ($e instanceof AuthenticationException) {
            return response()->json(['ok'=>false,'error'=>'Não autenticado','type'=>'AuthenticationException'], 401);
        }
        if ($e instanceof AccessDeniedHttpException) {
            return response()->json(['ok'=>false,'error'=>'Acesso negado','type'=>'AccessDeniedHttpException'], 403);
        }
        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return response()->json(['ok'=>false,'error'=>'Recurso não encontrado','type'=>'NotFound'], 404);
        }
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json(['ok'=>false,'error'=>'Método não permitido','type'=>'MethodNotAllowed'], 405);
        }
        if ($e instanceof BadRequestHttpException) {
            return response()->json(['ok'=>false,'error'=>'Requisição inválida','type'=>'BadRequest'], 400);
        }
        if ($e instanceof ThrottleRequestsException) {
            return response()->json(['ok'=>false,'error'=>'Muitas requisições','type'=>'Throttle'], 429);
        }
        if ($e instanceof HttpResponseException) {
            $resp = $e->getResponse();
            return response()->json([
                'ok'    => false,
                'error' => 'HTTP error',
                'type'  => 'HttpResponseException',
                'body'  => method_exists($resp, 'getContent') ? $resp->getContent() : null,
            ], method_exists($resp, 'getStatusCode') ? $resp->getStatusCode() : 500);
        }

        // Demais exceções → 500 (ou código do HttpException)
        $status = 500;
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode() ?: 500;
        }

        $payload = [
            'ok'    => false,
            'error' => $e->getMessage() ?: 'Erro interno',
            'type'  => (new \ReflectionClass($e))->getShortName(),
        ];

        // Se APP_DEBUG=true, incluir trace resumido
        if (config('app.debug')) {
            $payload['trace'] = collect($e->getTrace())->map(function ($t) {
                return [
                    'file' => $t['file'] ?? null,
                    'line' => $t['line'] ?? null,
                    'func' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                ];
            })->take(10);
        }

        return response()->json($payload, $status);
    }
}
