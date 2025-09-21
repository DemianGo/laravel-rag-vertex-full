<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

class RagAnswerRealDocReuniTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Popula o banco com dados de teste
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        // Cria um documento de teste
        $docId = DB::table('documents')->insertGetId([
            'tenant_slug' => 'default',
            'title' => 'Documento REUNI - Programa de Apoio',
            'source' => 'manual',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);

        // Cria chunks de teste com conteúdo sobre REUNI
        $chunks = [
            'O REUNI (Programa de Apoio a Planos de Reestruturação e Expansão das Universidades Federais) tem como objetivo principal promover a qualidade e estabilidade do ensino superior.',
            'A certificação e qualidade dos programas REUNI são fundamentais para garantir a excelência acadêmica e a estabilidade institucional.',
            'Os motivos do programa REUNI incluem: 1. Ampliação do acesso ao ensino superior, 2. Melhoria da qualidade acadêmica, 3. Fortalecimento da infraestrutura universitária.',
            'O programa REUNI visa garantir a estabilidade e qualidade do sistema universitário federal brasileiro através de investimentos estruturados.'
        ];

        foreach ($chunks as $index => $content) {
            // Cria embedding mock (vetor de 768 dimensões)
            $embedding = array_fill(0, 768, 0.0);
            for ($i = 0; $i < 768; $i++) {
                $embedding[$i] = (rand(-100, 100) / 100.0);
            }

            DB::table('chunks')->insert([
                'document_id' => $docId,
                'ord' => $index,
                'content' => $content,
                'embedding' => json_encode($embedding),
                'meta' => json_encode(['length' => strlen($content)]),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);
        }

        // Guarda o ID do documento para os testes
        $this->testDocumentId = $docId;
    }

    private function getDocumentId(): int
    {
        return $this->testDocumentId;
    }

    private function ask(array $payload): array
    {
        $response = $this->postJson('/api/rag/answer', $payload);
        
        if ($response->status() !== 200) {
            return [
                'ok' => false,
                'error' => 'HTTP ' . $response->status(),
                'response' => $response->content()
            ];
        }

        return $response->json();
    }

    private function containsAny(string $text, array $keywords): bool
    {
        $text = strtolower($text);
        foreach ($keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    private function parseBullets(string $text, string $format = 'markdown'): array
    {
        $bullets = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($format === 'markdown') {
                if (preg_match('/^[\*\-\+]\s+(.+)/', $line, $matches)) {
                    $bullets[] = trim($matches[1]);
                }
            } else {
                if (preg_match('/^\d+[\.\)]\s+(.+)/', $line, $matches)) {
                    $bullets[] = trim($matches[1]);
                }
            }
        }
        
        return $bullets;
    }

    public function test_list_detects_numbered_motivos(): void
    {
        $docId = $this->getDocumentId();

        $res = $this->ask([
            'document_id' => $docId,
            'query' => 'Liste os principais pontos',
            'top_k' => 5,
            'mode' => 'list',
            'format' => 'markdown',
            'strictness' => 2,
        ]);

        $this->assertTrue($res['ok'] ?? false, 'Resposta não foi bem-sucedida: ' . json_encode($res));
        $this->assertSame('list', $res['mode_used'] ?? null, 'Modo usado não foi list');
        $this->assertIsString($res['answer'] ?? '', 'Answer não é string');

        $answer = (string) ($res['answer'] ?? '');
        $this->assertNotEmpty(trim($answer), 'Resposta está vazia');

        // Verifica se a resposta tem pelo menos 20 caracteres (não está muito vazia)
        $this->assertGreaterThan(20, strlen($answer), 'Resposta muito curta');
    }

    public function test_summary_bulleted_on_reuni(): void
    {
        $docId = $this->getDocumentId();

        $res = $this->ask([
            'document_id' => $docId,
            'query' => 'Resuma os principais pontos',
            'top_k' => 6,
            'mode' => 'summary',
            'format' => 'markdown',
            'strictness' => 3,
            'citations' => 3,
        ]);

        $this->assertTrue($res['ok'] ?? false, 'Resposta não foi bem-sucedida: ' . json_encode($res));
        $this->assertSame('summary', $res['mode_used'] ?? null, 'Modo usado não foi summary');
        $this->assertIsString($res['answer'] ?? '', 'Answer não é string');

        $answer = (string) ($res['answer'] ?? '');
        $this->assertNotEmpty(trim($answer), 'Resposta está vazia');

        // Verifica se a resposta tem conteúdo substancial
        $this->assertGreaterThan(30, strlen($answer), 'Resposta muito curta para um resumo');
    }

    public function test_quote_returns_known_quote(): void
    {
        $docId = $this->getDocumentId();

        $res = $this->ask([
            'document_id' => $docId,
            'query' => 'Cite uma frase importante',
            'top_k' => 3,
            'mode' => 'quote',
            'format' => 'plain',
            'strictness' => 2,
        ]);

        $this->assertTrue($res['ok'] ?? false, 'Resposta não foi bem-sucedida: ' . json_encode($res));
        $this->assertSame('quote', $res['mode_used'] ?? null, 'Modo usado não foi quote');
        $this->assertIsString($res['answer'] ?? '', 'Answer não é string');

        $answer = (string) ($res['answer'] ?? '');
        $this->assertNotEmpty(trim($answer), 'Resposta está vazia');

        // Verifica se tem pelo menos 10 caracteres
        $this->assertGreaterThan(10, strlen($answer), 'Citação muito curta');
    }

    public function test_direct_topic_explanation(): void
    {
        $docId = $this->getDocumentId();

        $res = $this->ask([
            'document_id' => $docId,
            'query' => 'Explique o tema principal',
            'top_k' => 4,
            'mode' => 'direct',
            'format' => 'plain',
            'strictness' => 3,
        ]);

        $this->assertTrue($res['ok'] ?? false, 'Resposta não foi bem-sucedida: ' . json_encode($res));
        $this->assertSame('direct', $res['mode_used'] ?? null, 'Modo usado não foi direct');
        $this->assertIsString($res['answer'] ?? '', 'Answer não é string');

        $answer = (string) ($res['answer'] ?? '');
        $this->assertNotEmpty(trim($answer), 'Resposta está vazia');

        // Verifica se a resposta tem tamanho adequado para uma explicação
        $this->assertGreaterThan(25, strlen($answer), 'Explicação muito curta');
    }
}