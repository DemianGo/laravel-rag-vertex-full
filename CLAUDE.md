REGRAS DE TRABALHO (curto):
- Envie apenas PATCH mínimo (unified diff). Nunca arquivo inteiro.
- Não faça varredura global. Leia só arquivos/linhas que eu pedir.
- Não mude env/deps/config sem solicitação explícita.
- Mantenha contrato dos testes: quote entre aspas; summary 3–7 bullets (ou fallback ≥1 bullet + ≥120 chars + palavras-chave); mode_used sempre.
- Após cada patch eu rodo: ./vendor/bin/phpunit tests/Feature/RagAnswerRealDocReuniTest.php
- Se quebrar, mande novo PATCH mínimo corrigindo.
- Aguarde eu indicar arquivos/linhas; responda sempre só com o PATCH.
