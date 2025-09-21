<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RagFixturesSeeder extends Seeder
{
    public function run(): void
    {
        // Doc p/ SUMMARY (frases curtas e distintas)
        $docSummaryId = 9001;
        DB::table('documents')->updateOrInsert(['id' => $docSummaryId], ['title' => 'Fixture Summary']);
        $summaryChunks = [
            [ 'id' => 900100, 'document_id' => $docSummaryId, 'ord' => 0, 'content' =>
                "Este documento descreve um produto hipotético com foco em qualidade, estabilidade e custo-benefício. " .
                "O processo de produção é padronizado e auditado, garantindo consistência entre lotes. " .
                "Relatos de profissionais reforçam eficácia e previsibilidade do tratamento." ],
            [ 'id' => 900101, 'document_id' => $docSummaryId, 'ord' => 1, 'content' =>
                "Laudos analíticos confirmam concentração declarada. " .
                "Há variedade de formulações para atender diferentes necessidades clínicas." ],
        ];
        foreach ($summaryChunks as $c) {
            DB::table('chunks')->updateOrInsert(['id' => $c['id']], $c);
        }

        // Doc p/ LIST (itens numerados)
        $docListId = 9002;
        DB::table('documents')->updateOrInsert(['id' => $docListId], ['title' => 'Fixture List']);
        $listText = "1. Qualidade auditada\n2. Estabilidade comprovada\n3. Preço competitivo\n4. Variedade de concentrações\n5. Cobertura de indicações";
        $listChunks = [
            [ 'id' => 900200, 'document_id' => $docListId, 'ord' => 0, 'content' => $listText ],
        ];
        foreach ($listChunks as $c) {
            DB::table('chunks')->updateOrInsert(['id' => $c['id']], $c);
        }

        // Doc p/ QUOTE (com aspas)
        $docQuoteId = 9003;
        DB::table('documents')->updateOrInsert(['id' => $docQuoteId], ['title' => 'Fixture Quote']);
        $quoteChunks = [
            [ 'id' => 900300, 'document_id' => $docQuoteId, 'ord' => 0, 'content' =>
                'A especialista afirmou: “O produto mantém o padrão entre lotes e entrega a concentração indicada.”' ],
        ];
        foreach ($quoteChunks as $c) {
            DB::table('chunks')->updateOrInsert(['id' => $c['id']], $c);
        }

        // Doc p/ TABLE (pares chave: valor)
        $docTableId = 9004;
        DB::table('documents')->updateOrInsert(['id' => $docTableId], ['title' => 'Fixture Table']);
        $tableChunks = [
            [ 'id' => 900400, 'document_id' => $docTableId, 'ord' => 0, 'content' =>
                "Concentração: 100 mg/mL\nProcesso: CO2 supercrítico\nLaudo: Disponível por lote" ],
        ];
        foreach ($tableChunks as $c) {
            DB::table('chunks')->updateOrInsert(['id' => $c['id']], $c);
        }
    }
}
