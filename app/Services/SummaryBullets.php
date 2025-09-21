<?php
// app/Services/SummaryBullets.php
//
// Genérico para qualquer documento (document-agnostic).
// - Remove cabeçalhos/slogans/fragmentos
// - Quebra sequências numeradas coladas
// - Clusters de tópico para evitar redundância
// - MMR (relevância x diversidade) + limite 1 por tópico
// - Testimonial só se necessário para atingir o mínimo (no final)

namespace App\Services;

class SummaryBullets
{
    /**
     * @param array<int,array{text:string, chunk_id?:int, ord?:int, score?:float}> $candidates
     * @return array{bullets: array<int,array{text:string, chunk_id?:int, ord?:int}>, sources: array<int,array{document_id?:int, id?:int, ord?:int}>}
     */
    public function build(array $candidates, int $min = 3, int $max = 5, int $maxCitations = 3): array
    {
        $clean = [];
        foreach ($candidates as $i => $c) {
            $raw = trim($c['text'] ?? '');
            if ($raw === '') continue;

            foreach ($this->splitInlineNumberedSegments($raw) as $seg) {
                $txt = $this->normalizeWhitespace($seg);
                $txt = $this->stripLeadingNumbering($txt);
                $txt = preg_replace('/\bCRM\s*\d+\b.*$/iu', '', $txt) ?? $txt;
                $txt = rtrim($txt, " \t\n\r\0\x0B.;");

                if ($this->isPromo($txt)) continue;
                if ($this->isHeading($txt)) continue;
                if ($this->isJunk($txt)) continue;

                $score = $c['score'] ?? 0.0;
                if ($score <= 0.0) $score = $this->scoreSentence($txt);

                $tokens = $this->tokenize($txt);
                $tag    = $this->topicTag($tokens, $txt);

                $clean[] = [
                    'text' => $this->capLength($txt, 220),
                    'chunk_id' => $c['chunk_id'] ?? null,
                    'ord' => $c['ord'] ?? $i,
                    'score' => $score,
                    '_tokens' => $tokens,
                    '_topic'  => $tag,
                    '_origIdx' => $i,
                ];
            }
        }

        if (empty($clean)) {
            $clean = array_values(array_map(function($c, $i){
                $t = trim($c['text'] ?? '');
                return [
                    'text' => $this->capLength($t, 220),
                    'chunk_id' => $c['chunk_id'] ?? null,
                    'ord' => $c['ord'] ?? $i,
                    'score' => 0.0,
                    '_tokens' => $this->tokenize($t),
                    '_topic'  => 'misc',
                    '_origIdx' => $i,
                ];
            }, array_slice($candidates, 0, max($min, 3)), range(0, max($min, 3))));
        }

        usort($clean, function($a, $b){
            if (($b['score'] <=> $a['score']) !== 0) return $b['score'] <=> $a['score'];
            return $a['_origIdx'] <=> $b['_origIdx'];
        });

        $picked = $this->selectDiversified($clean, $min, $max);
        $picked = $this->postProcessTestimonial($picked, $clean, $min, $max);

        $bullets = [];
        $sources = [];
        foreach ($picked as $i => $p) {
            $bullets[] = [
                'text' => $p['text'],
                'chunk_id' => $p['chunk_id'] ?? null,
                'ord' => $p['ord'] ?? null,
            ];
            if ($i < $maxCitations && isset($p['ord'])) {
                $sources[] = [
                    'document_id' => null,
                    'id' => $p['chunk_id'] ?? null,
                    'ord' => $p['ord'],
                ];
            }
        }

        return ['bullets' => $bullets, 'sources' => $sources];
    }

    private function selectDiversified(array $clean, int $min, int $max): array
    {
        $lambda = 0.75;
        $picked = [];
        $topicsCount = [];
        $topicCap = [
            'coa'                 => 1,
            'certifications'      => 1,
            'quality_process'     => 1,
            'portfolio'           => 1,
            'dosage_consistency'  => 1,
            'misc'                => 1,
            'testimonial'         => 0,
        ];

        while (count($picked) < $max && !empty($clean)) {
            $bestIdx = null; $bestScore = -INF;

            foreach ($clean as $idx => $cand) {
                $topic = $cand['_topic'] ?? 'misc';
                if (($topicsCount[$topic] ?? 0) >= ($topicCap[$topic] ?? 1)) continue;

                $maxSim = 0.0;
                foreach ($picked as $p) {
                    $sim = $this->jaccard($cand['_tokens'], $p['_tokens']);
                    if ($sim > $maxSim) $maxSim = $sim;
                }
                $topicPenalty = ($topic === 'testimonial') ? 0.15 : 0.0;
                $mmr = ($lambda * $cand['score'] - (1.0 - $lambda) * $maxSim) - $topicPenalty;

                if ($mmr > $bestScore) { $bestScore = $mmr; $bestIdx = $idx; }
            }

            if ($bestIdx === null) {
                if (count($picked) >= $min) break;
                if ($topicCap['testimonial'] === 0) { $topicCap['testimonial'] = 1; continue; }
                $bestIdx = $this->bestIgnoringCap($clean, $picked, $lambda);
                if ($bestIdx === null) break;
            }

            $chosen = $clean[$bestIdx];
            $picked[] = $chosen;
            $topicChosen = $chosen['_topic'] ?? 'misc';
            $topicsCount[$topicChosen] = ($topicsCount[$topicChosen] ?? 0) + 1;

            array_splice($clean, $bestIdx, 1);

            $clean = array_values(array_filter($clean, function($c) use ($chosen) {
                return $this->jaccard($c['_tokens'], $chosen['_tokens']) <= 0.50;
            }));
        }

        $i = 0;
        while (count($picked) < $min && $i < count($clean)) {
            $picked[] = $clean[$i++];
        }

        return $picked;
    }

    private function bestIgnoringCap(array $clean, array $picked, float $lambda): ?int
    {
        $bestIdx = null; $bestScore = -INF;
        foreach ($clean as $idx => $cand) {
            $maxSim = 0.0;
            foreach ($picked as $p) {
                $sim = $this->jaccard($cand['_tokens'], $p['_tokens']);
                if ($sim > $maxSim) $maxSim = $sim;
            }
            $topicPenalty = (($cand['_topic'] ?? '') === 'testimonial') ? 0.15 : 0.0;
            $mmr = ($lambda * $cand['score'] - (1.0 - $lambda) * $maxSim) - $topicPenalty;
            if ($mmr > $bestScore) { $bestScore = $mmr; $bestIdx = $idx; }
        }
        return $bestIdx;
    }

    private function postProcessTestimonial(array $picked, array $clean, int $min, int $max): array
    {
        if (empty($picked)) return $picked;

        $nonTest = array_values(array_filter($picked, fn($p) => ($p['_topic'] ?? 'misc') !== 'testimonial'));
        $tests   = array_values(array_filter($picked, fn($p) => ($p['_topic'] ?? 'misc') === 'testimonial'));

        if (count($nonTest) >= $min) {
            $picked = $nonTest;
        } else {
            $picked = $nonTest;
            if (!empty($tests)) $picked[] = $tests[0];
            else {
                foreach ($clean as $c) {
                    if (($c['_topic'] ?? 'misc') === 'testimonial') { $picked[] = $c; break; }
                }
            }
        }

        if (count($picked) > $max) $picked = array_slice($picked, 0, $max);
        return $picked;
    }

    private function topicTag(array $tokens, string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');

        if (preg_match('/(certificado de an[aá]lise|\bcoa\b|lote|estabil|quantidade.*indicad)/u', $t)) {
            return 'coa';
        }
        if (preg_match('/(certifica[cç][aã]o|certificado(?! de an[aá]lise)|gmp|iso|anvisa|boas pr[aá]ticas)/u', $t)) {
            return 'certifications';
        }
        if (preg_match('/(padroniz|processo|produ[cç][aã]o|qualidade premium|qualidade)/u', $t)) {
            return 'quality_process';
        }
        if (preg_match('/(linha diversificada|diversas especialidades|portf[oó]lio|variedade de produtos)/u', $t)) {
            return 'portfolio';
        }
        if (preg_match('/(dosag|concentra[cç][aã]o|\bmg\b|\bml\b|\bcbn\b|\bcbg\b|\bcbd\b)/u', $t)) {
            return 'dosage_consistency';
        }
        if (preg_match('/(como m[eé]dic[ao]|depoiment|^“|^")/u', $t)) {
            return 'testimonial';
        }
        return 'misc';
    }

    private function isPromo(string $s): bool
    {
        $u = mb_strtoupper($s, 'UTF-8');
        $capsRatio = $this->uppercaseRatio($s);
        if ($capsRatio > 0.6) return true;
        $bad = ['DESCUBRA','LINHA PREMIUM','MATERIAL DESTINADO','EXCLUSIVAMENTE','PROFISSIONAIS DE SAÚDE','CONTEÚDO PROMOCIONAL'];
        foreach ($bad as $kw) if (mb_strpos($u, $kw) !== false) return true;
        return false;
    }

    private function isHeading(string $s): bool
    {
        $u = mb_strtolower($s, 'UTF-8');
        $h = ['motivos para integrar','motivos para escolher','sumário','introdução','descubra a linha','apresentação','sobre a'];
        foreach ($h as $kw) if (mb_strpos($u, $kw) !== false) return true;
        if (!preg_match('/[\.!\?]/u', $s)) {
            $wc = max(1, count(preg_split('/\s+/u', trim($s))));
            if ($wc <= 6) return true;
        }
        return false;
    }

    private function isJunk(string $s): bool
    {
        $trim = trim($s);
        if ($trim === '') return true;
        if (mb_strlen($trim, 'UTF-8') < 30) return true;
        $start = ltrim($trim, "“\"'«»‘’‚“”");
        $first = mb_substr($start, 0, 1, 'UTF-8');
        if ($first !== '' && preg_match('/[a-zá-ú]/u', $first)) return true;
        if (preg_match('/^\d{1,3}\s*$/u', $trim)) return true;
        return false;
    }

    private function stripLeadingNumbering(string $s): string
    {
        $s = preg_replace('/^\s*\d{1,3}(?:\s*[)\.\-–—]\s+|\s+)/u', '', $s) ?? $s;
        $s = preg_replace('/^\s*(?:item|motivo)\s*\d{1,3}\s*[:\-–—]\s+/iu', '', $s) ?? $s;
        return $s;
    }

    private function splitInlineNumberedSegments(string $s): array
    {
        $s = $this->normalizeWhitespace($s);
        $marked = preg_replace('/\s+(?=\d{1,3}\s+\p{Lu})/u', ' ||| ', $s) ?? $s;
        $parts = preg_split('/\s*\|\|\|\s*/u', $marked, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_map('trim', $parts));
    }

    private function normalizeWhitespace(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function capLength(string $s, int $max): string
    {
        if (mb_strlen($s, 'UTF-8') <= $max) return $s;
        $s = mb_substr($s, 0, $max, 'UTF-8');
        return rtrim($s, " \t\n\r\0\x0B.,;:-").'…';
    }

    private function scoreSentence(string $txt): float
    {
        $toks = $this->tokenize($txt);
        $len = count($toks);
        $hits = 0;
        $kw = ['qualidad','certific','pacient','tratament','estabil','custo','benef','médic','medic','process','produ','padroniz','linha','anális'];
        foreach ($toks as $t) {
            foreach ($kw as $k) if (mb_strpos($t, $k) !== false) { $hits++; break; }
        }
        return $hits * 1.0 + min($len, 30) / 30.0;
    }

    /** @return array<int,string> */
    private function tokenize(string $s): array
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;
        $parts = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) return [];
        $stop = ['de','da','do','das','dos','e','a','o','as','os','para','por','em','um','uma','no','na','nos','nas','ao','à','com','que','se','é','ser','são'];
        return array_values(array_diff($parts, $stop));
    }

    /** @param array<int,string> $a @param array<int,string> $b */
    private function jaccard(array $a, array $b): float
    {
        if (empty($a) && empty($b)) return 0.0;
        $sa = array_unique($a); $sb = array_unique($b);
        $inter = array_intersect($sa, $sb);
        $union = array_unique(array_merge($sa, $sb));
        return count($inter) / max(1, count($union));
    }

    private function uppercaseRatio(string $s): float
    {
        $letters = preg_replace('/[^A-Za-zÀ-ÿ]/u', '', $s) ?? '';
        if ($letters === '') return 0.0;
        $upper = preg_replace('/[^A-ZÀ-Ý]/u', '', $letters) ?? '';
        return mb_strlen($upper, 'UTF-8') / max(1, mb_strlen($letters, 'UTF-8'));
    }
}
