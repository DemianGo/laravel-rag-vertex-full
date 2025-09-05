<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class VertexController extends Controller
{
    /**
     * GET /api/vertex/generate?prompt=...
     * Opcional: ?model=gemini-1.5-flash (default)
     */
    public function generate(Request $request)
    {
        $prompt = trim((string) $request->query("prompt", ""));
        if ($prompt === "") {
            return response()->json(["ok"=>false,"error"=>"Parâmetro \"prompt\" é obrigatório"], 400);
        }

        $cfg      = config("services.vertex");
        $project  = $cfg["project"]  ?? env("VERTEX_PROJECT", "");
        $location = $cfg["location"] ?? env("VERTEX_LOCATION", "us-central1");
        $model    = $request->query("model", env("VERTEX_GENERATION_MODEL", "gemini-1.5-flash"));

        if ($project === "" || $location === "") {
            return response()->json(["ok"=>false,"error"=>"Config Vertex ausente (project/location)"], 500);
        }

        // Token via gcloud ADC (sem JSON), como já usamos no embedding
        $token = trim(shell_exec("gcloud auth application-default print-access-token 2>/dev/null") ?? "");
        if ($token === "") {
            return response()->json(["ok"=>false,"error"=>"ADC não encontrado. Rode: gcloud auth application-default login"], 500);
        }

        // Endpoint do Vertex (Generative AI - generateContent)
        $url = sprintf(
            "https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent",
            $location, $project, $location, $model
        );

        $body = [
            "contents" => [[
                "role"  => "user",
                "parts" => [["text" => $prompt]],
            ]],
        ];

        try {
            $http = new Client(["timeout" => 30]);
            $res  = $http->post($url, [
                "headers" => [
                    "Authorization" => "Bearer {$token}",
                    "Content-Type"  => "application/json",
                    "Accept"        => "application/json",
                ],
                "json" => $body,
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $text = "";

            if (isset($data["candidates"][0]["content"]["parts"])) {
                foreach ($data["candidates"][0]["content"]["parts"] as $p) {
                    if (isset($p["text"])) { $text .= $p["text"]; }
                }
            }

            return response()->json([
                "ok"    => true,
                "model" => $model,
                "text"  => $text,
                "raw"   => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error("Vertex generate error: ".$e->getMessage());
            return response()->json(["ok"=>false,"error"=>$e->getMessage()], 500);
        }
    }
}
