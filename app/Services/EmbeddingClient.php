<?php
namespace App\Services;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class EmbeddingClient
{
    public function embed(string $projectId, string $location, string $model, string $text, int $dim = 768, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
        $creds  = ApplicationDefaultCredentials::getCredentials($scopes);
        $token  = $creds->fetchAuthToken()['access_token'] ?? null;
        if (!$token) throw new \RuntimeException('Sem access token. Rode: gcloud auth application-default login');

        $host = ($location === 'global') ? 'aiplatform.googleapis.com' : sprintf('%s-aiplatform.googleapis.com', $location);
        $endpoint = sprintf('https://%s/v1/projects/%s/locations/%s/publishers/google/models/%s:predict', $host, $projectId, $location, $model);

        $body = [
            'instances' => [[
                'content'   => $text,
                'task_type' => $taskType,
            ]],
            'parameters' => [
                'outputDimensionality' => $dim
            ],
        ];

        $client = new Client(['timeout' => 30]);
        try {
            $res = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $body,
            ]);
            $json = json_decode((string)$res->getBody(), true);
            $values = $json['predictions'][0]['embeddings']['values'] ?? null;
            if (!is_array($values)) throw new \RuntimeException('Resposta sem embeddings.values');
            return $values;
        } catch (RequestException $e) {
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body   = $e->getResponse() ? (string)$e->getResponse()->getBody() : '';
            throw new \RuntimeException("Erro Embedding HTTP {$status}: {$body}");
        }
    }
}
