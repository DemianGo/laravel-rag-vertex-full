<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\VertexClient;
use App\Models\Chunk;
use Exception;

class FixMissingEmbeddings extends Command
{
    protected $signature = 'rag:fix-embeddings
                          {--dry-run : Show what would be fixed without making changes}
                          {--batch-size=10 : Number of chunks to process at once}
                          {--tenant= : Specific tenant to process}';

    protected $description = 'Fix chunks that are missing embeddings by regenerating them';

    private VertexClient $vertexClient;
    private int $batchSize;
    private bool $dryRun;
    private array $stats = [
        'total_chunks' => 0,
        'fixed_chunks' => 0,
        'failed_chunks' => 0,
        'skipped_chunks' => 0,
    ];

    public function handle()
    {
        $this->info('🔍 RAG Missing Embeddings Fixer');
        $this->info('================================');

        // Initialize services
        $this->vertexClient = app(VertexClient::class);
        $this->batchSize = (int) $this->option('batch-size');
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Find chunks without embeddings
        $query = DB::table('chunks')->whereNull('embedding');

        if ($tenant = $this->option('tenant')) {
            // Join with documents to filter by tenant
            $query->join('documents', 'chunks.document_id', '=', 'documents.id')
                  ->where('documents.tenant_slug', $tenant)
                  ->select('chunks.id', 'chunks.document_id', 'chunks.ord', 'chunks.content');
        }

        $missingChunks = $query->get(['id', 'document_id', 'ord', 'content']);
        $this->stats['total_chunks'] = $missingChunks->count();

        if ($this->stats['total_chunks'] === 0) {
            $this->info('✅ All chunks already have embeddings!');
            return 0;
        }

        $this->info("📊 Found {$this->stats['total_chunks']} chunks missing embeddings");

        // Process chunks in batches
        $chunks = $missingChunks->chunk($this->batchSize);

        foreach ($chunks as $batch) {
            $this->processBatch($batch);
        }

        // Show final results
        $this->showResults();

        return 0;
    }

    private function processBatch($chunks)
    {
        $this->info("🔄 Processing batch of {$chunks->count()} chunks...");

        if ($this->dryRun) {
            foreach ($chunks as $chunk) {
                $this->line("  - Would fix chunk {$chunk->id} (doc {$chunk->document_id}, ord {$chunk->ord})");
                $this->stats['skipped_chunks']++;
            }
            return;
        }

        // Prepare texts for embedding
        $texts = $chunks->pluck('content')->toArray();
        $chunkIds = $chunks->pluck('id')->toArray();

        try {
            // Generate embeddings using VertexClient
            $embeddings = $this->vertexClient->embed($texts);

            // Update chunks with embeddings
            foreach ($chunks as $index => $chunk) {
                if (isset($embeddings[$index])) {
                    $embedding = $embeddings[$index];

                    DB::table('chunks')
                        ->where('id', $chunk->id)
                        ->update([
                            'embedding' => json_encode($embedding),
                            'updated_at' => now()
                        ]);

                    $this->line("  ✅ Fixed chunk {$chunk->id}");
                    $this->stats['fixed_chunks']++;
                } else {
                    $this->error("  ❌ Failed to generate embedding for chunk {$chunk->id}");
                    $this->stats['failed_chunks']++;
                }
            }

        } catch (Exception $e) {
            $this->error("  💥 Batch failed: " . $e->getMessage());
            Log::error('FixMissingEmbeddings batch failed', [
                'chunk_ids' => $chunkIds,
                'error' => $e->getMessage()
            ]);

            $this->stats['failed_chunks'] += $chunks->count();
        }
    }

    private function showResults()
    {
        $this->info('');
        $this->info('📈 Final Results:');
        $this->info('================');
        $this->info("Total chunks found: {$this->stats['total_chunks']}");
        $this->info("Fixed chunks: {$this->stats['fixed_chunks']}");
        $this->info("Failed chunks: {$this->stats['failed_chunks']}");
        $this->info("Skipped chunks: {$this->stats['skipped_chunks']}");

        if ($this->stats['failed_chunks'] > 0) {
            $this->warn("⚠️  {$this->stats['failed_chunks']} chunks could not be fixed. Check logs for details.");
        }

        if (!$this->dryRun && $this->stats['fixed_chunks'] > 0) {
            $this->info('🎉 Embedding fixes completed successfully!');

            // Update database statistics
            DB::statement('ANALYZE chunks');
            $this->info('📊 Database statistics updated');
        }
    }
}