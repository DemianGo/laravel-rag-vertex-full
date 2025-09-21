<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RagAnswerMatrixTest extends TestCase
{
    protected function shouldRun(): bool
    {
        return true; // Forçado para teste
    }

    public function test_full_matrix_or_skip(): void
    {
        if (!$this->shouldRun()) {
            $this->markTestSkipped('RAG_FULL_MATRIX=false');
            return;
        }

        $docId = $this->ensureDocId();

        $modes   = ['direct','summary','list','quote','table'];          // 5
        $formats = ['markdown','html','plain'];                          // 3
        $lengths = ['auto','short','medium','long'];                     // 4
        $stricts = [3,2,1];                                             // 3 (sem 0 para evitar variações soltas)
        $cits    = [0,3];                                               // 2

        $count = 0;
        foreach ($modes as $mode) {
            foreach ($formats as $format) {
                foreach ($lengths as $length) {
                    foreach ($stricts as $strictness) {
                        foreach ($cits as $cit) {
                            $payload = [
                                'document_id' => $docId,
                                'query'       => $this->queryForMode($mode),
                                'mode'        => $mode,
                                'top_k'       => 6,
                                'format'      => $format,
                                'length'      => $length,
                                'strictness'  => $strictness,
                                'citations'   => $cit,
                            ];
                            $res = $this->json('POST','/rag/answer',$payload)->assertStatus(200)->json();
                            $this->assertTrue($res['ok'] ?? false, "$mode/$format/$length/s$strictness/c$cit: ok=false");
                            $this->assertSame($mode, $res['mode_used'] ?? null, "$mode/$format/$length: mode_used");
                            $this->assertIsString($res['answer'] ?? '', "answer ausente");
                            $count++;
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(300, $count, 'Matriz não executada como esperado');
    }

    // ---------- infra mínima (igual ao outro teste) ----------
    protected function ensureDocId(): int
    {
        $pref = (int) env('RAG_TEST_DOC_ID', 37);
        $this->ensureChunksTable();

        $row = DB::selectOne("SELECT COUNT(*) AS c FROM chunks WHERE document_id = ?", [$pref]);
        if ($row && (int)$row->c > 0) return $pref;

        $docId = 999;
        $exists = DB::selectOne("SELECT COUNT(*) AS c FROM chunks WHERE document_id = ?", [$docId]);
        if (!$exists || (int)$exists->c === 0) $this->seedGenericDoc($docId);
        return $docId;
    }

    protected function ensureChunksTable(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS chunks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            ord INTEGER NOT NULL,
            content TEXT NOT NULL
        )');
    }

    protected function seedGenericDoc(int $docId): void
    {
        $rows = [
            [0, "Documento genérico: portfólio e qualidade padronizada."],
            [1, "Certificações e auditorias asseguram boas práticas."],
            [2, "Estabilidade por certificado de análise por lote; rastreabilidade."],
            [3, "Compatibilidade entre declaração de embalagem e verificação interna."],
            [4, "Processo com controle, filtragem e purificação; registros de etapa."],
            [5, "Linha diversificada para necessidades distintas; opções concentradas."],
            [6, "“Depoimento profissional: previsibilidade na prática clínica.”"],
            [7, "Chave: Processo; Valor: Padronizado. Chave: Portfólio; Valor: Variado."],
        ];
        foreach ($rows as [$ord, $content]) {
            DB::table('chunks')->insert([
                'document_id' => $docId,
                'ord' => $ord,
                'content' => $content
            ]);
        }
    }

    private function queryForMode(string $mode): string
    {
        return match ($mode) {
            'summary' => 'Resuma o documento em 3 a 5 pontos objetivos.',
            'list'    => 'Liste os principais pontos em formato numerado.',
            'quote'   => 'Cite um trecho relevante entre aspas, literal.',
            'table'   => 'Mostre como pares chave:valor principais.',
            default   => 'Explique o assunto central do documento com base no contexto.',
        };
    }
}
