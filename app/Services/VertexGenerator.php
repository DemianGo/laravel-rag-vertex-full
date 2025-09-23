<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use App\Services\VertexClient;
use App\Services\RagCache;
use App\Services\RagMetrics;
use Throwable;

/**
 * Advanced Vertex Generator Service
 *
 * Provides sophisticated contextual generation capabilities for RAG systems
 * with support for different generation strategies, prompt optimization,
 * and enterprise-grade response generation with citations and validation.
 */
class VertexGenerator
{
    private VertexClient $vertexClient;
    private RagCache $cache;
    private RagMetrics $metrics;
    private array $config;

    public function __construct(VertexClient $vertexClient, RagCache $cache, RagMetrics $metrics)
    {
        $this->vertexClient = $vertexClient;
        $this->cache = $cache;
        $this->metrics = $metrics;
        $this->config = config('rag.generation', []);
    }

    /**
     * Generate contextual response using RAG pipeline
     *
     * @param string $query User query
     * @param array $contexts Retrieved context chunks
     * @param array $options Generation options
     * @return array Generated response with metadata
     */
    public function generate(string $query, array $contexts, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Validate inputs
            if (empty($query) || empty($contexts)) {
                throw new \InvalidArgumentException('Query and contexts are required');
            }

            // Check cache first
            $cacheKey = $this->cache->getCacheKey('generation', [
                'query' => md5($query),
                'contexts' => md5(serialize(array_column($contexts, 'id'))),
                'options' => md5(serialize($options))
            ]);

            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                $this->metrics->recordGeneration($query, $contexts, 0, true);
                return $cached;
            }

            // Select generation strategy
            $strategy = $options['strategy'] ?? $this->config['strategy'] ?? 'contextual';

            $result = match ($strategy) {
                'contextual' => $this->generateContextual($query, $contexts, $options),
                'adaptive' => $this->generateAdaptive($query, $contexts, $options),
                'streaming' => $this->generateStreaming($query, $contexts, $options),
                'multi_turn' => $this->generateMultiTurn($query, $contexts, $options),
                default => $this->generateContextual($query, $contexts, $options)
            };

            // Add generation metadata
            $duration = (microtime(true) - $startTime) * 1000;
            $result['metadata'] = array_merge($result['metadata'] ?? [], [
                'generation_strategy' => $strategy,
                'duration_ms' => round($duration, 2),
                'context_count' => count($contexts),
                'query_length' => strlen($query),
                'cached' => false,
                'timestamp' => now()->toISOString()
            ]);

            // Cache the result
            $this->cache->put($cacheKey, $result, $this->config['cache_ttl'] ?? 3600);

            // Record metrics
            $this->metrics->recordGeneration($query, $contexts, $duration, false);

            return $result;

        } catch (Throwable $e) {
            Log::error('Context generation failed', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 200),
                'context_count' => count($contexts),
                'trace' => $e->getTraceAsString()
            ]);

            $this->metrics->recordError('generation', $e);

            return [
                'success' => false,
                'error' => 'Generation failed: ' . $e->getMessage(),
                'fallback' => $this->getFallbackResponse($query),
                'metadata' => [
                    'error' => true,
                    'duration_ms' => (microtime(true) - $startTime) * 1000,
                    'timestamp' => now()->toISOString()
                ]
            ];
        }
    }

    /**
     * Standard contextual generation
     */
    private function generateContextual(string $query, array $contexts, array $options = []): array
    {
        // Optimize contexts
        $optimizedContexts = $this->optimizeContexts($contexts, $options);

        // Build prompt
        $prompt = $this->buildContextualPrompt($query, $optimizedContexts, $options);

        // Generate response
        $response = $this->vertexClient->generate($prompt, [
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? $this->config['temperature'] ?? 0.3,
            'top_p' => $options['top_p'] ?? $this->config['top_p'] ?? 0.8,
            'model' => $options['model'] ?? $this->config['model'] ?? 'gemini-1.5-flash'
        ]);

        // Post-process response
        $processedResponse = $this->postProcessResponse($response, $optimizedContexts, $options);

        return [
            'success' => true,
            'response' => $processedResponse['text'],
            'citations' => $processedResponse['citations'],
            'confidence' => $processedResponse['confidence'],
            'sources' => $this->extractSources($optimizedContexts),
            'metadata' => [
                'prompt_tokens' => strlen($prompt) / 4, // Approximate
                'completion_tokens' => strlen($response) / 4,
                'contexts_used' => count($optimizedContexts),
                'generation_method' => 'contextual'
            ]
        ];
    }

    /**
     * Adaptive generation that adjusts based on query complexity
     */
    private function generateAdaptive(string $query, array $contexts, array $options = []): array
    {
        // Analyze query complexity
        $complexity = $this->analyzeQueryComplexity($query);

        // Adjust parameters based on complexity
        $adaptedOptions = $this->adaptGenerationParameters($complexity, $options);

        // Use different prompting strategies
        if ($complexity['type'] === 'simple') {
            return $this->generateSimple($query, $contexts, $adaptedOptions);
        } elseif ($complexity['type'] === 'complex') {
            return $this->generateComplex($query, $contexts, $adaptedOptions);
        } else {
            return $this->generateContextual($query, $contexts, $adaptedOptions);
        }
    }

    /**
     * Streaming generation for real-time responses
     */
    private function generateStreaming(string $query, array $contexts, array $options = []): array
    {
        // Note: This is a simplified implementation
        // In a real streaming setup, you'd use WebSockets or Server-Sent Events

        $optimizedContexts = $this->optimizeContexts($contexts, $options);
        $prompt = $this->buildContextualPrompt($query, $optimizedContexts, $options);

        // Generate in chunks
        $chunks = $this->generateInChunks($prompt, $options);

        $fullResponse = implode('', $chunks);
        $processedResponse = $this->postProcessResponse($fullResponse, $optimizedContexts, $options);

        return [
            'success' => true,
            'response' => $processedResponse['text'],
            'citations' => $processedResponse['citations'],
            'confidence' => $processedResponse['confidence'],
            'sources' => $this->extractSources($optimizedContexts),
            'streaming' => true,
            'chunks' => count($chunks),
            'metadata' => [
                'generation_method' => 'streaming',
                'chunk_count' => count($chunks)
            ]
        ];
    }

    /**
     * Multi-turn conversation generation
     */
    private function generateMultiTurn(string $query, array $contexts, array $options = []): array
    {
        $conversationHistory = $options['history'] ?? [];

        // Build conversation-aware prompt
        $prompt = $this->buildConversationalPrompt($query, $contexts, $conversationHistory, $options);

        $response = $this->vertexClient->generate($prompt, [
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.4, // Slightly higher for conversations
            'top_p' => $options['top_p'] ?? $this->config['top_p'] ?? 0.8,
        ]);

        $processedResponse = $this->postProcessResponse($response, $contexts, $options);

        return [
            'success' => true,
            'response' => $processedResponse['text'],
            'citations' => $processedResponse['citations'],
            'confidence' => $processedResponse['confidence'],
            'sources' => $this->extractSources($contexts),
            'conversation_turn' => count($conversationHistory) + 1,
            'metadata' => [
                'generation_method' => 'multi_turn',
                'history_length' => count($conversationHistory)
            ]
        ];
    }

    /**
     * Optimize contexts for generation
     */
    private function optimizeContexts(array $contexts, array $options = []): array
    {
        // Limit context by token count
        $maxTokens = $options['max_context_tokens'] ?? $this->config['max_context_tokens'] ?? 8000;
        $maxChunks = $options['max_context_chunks'] ?? $this->config['max_context_chunks'] ?? 10;

        // Sort by relevance score
        usort($contexts, fn($a, $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));

        $optimizedContexts = [];
        $tokenCount = 0;

        foreach (array_slice($contexts, 0, $maxChunks) as $context) {
            $contextTokens = strlen($context['content']) / 4; // Approximate

            if ($tokenCount + $contextTokens > $maxTokens) {
                break;
            }

            $tokenCount += $contextTokens;
            $optimizedContexts[] = $context;
        }

        return $optimizedContexts;
    }

    /**
     * Build contextual prompt
     */
    private function buildContextualPrompt(string $query, array $contexts, array $options = []): string
    {
        $systemPrompt = $options['system_prompt'] ?? $this->config['system_prompt'] ??
            'You are a helpful AI assistant that answers questions based on the provided context. Always cite your sources and be concise.';

        $contextText = $this->formatContexts($contexts);

        $prompt = "{$systemPrompt}\n\n";
        $prompt .= "Context Information:\n{$contextText}\n\n";
        $prompt .= "Question: {$query}\n\n";
        $prompt .= "Please provide a comprehensive answer based on the context above. Include relevant citations and indicate if any information is missing or uncertain.\n\n";
        $prompt .= "Answer:";

        return $prompt;
    }

    /**
     * Build conversational prompt with history
     */
    private function buildConversationalPrompt(string $query, array $contexts, array $history, array $options = []): string
    {
        $systemPrompt = "You are a helpful AI assistant engaged in a conversation. Use the provided context and conversation history to give relevant, consistent answers.";

        $contextText = $this->formatContexts($contexts);
        $historyText = $this->formatConversationHistory($history);

        $prompt = "{$systemPrompt}\n\n";
        $prompt .= "Context Information:\n{$contextText}\n\n";

        if (!empty($historyText)) {
            $prompt .= "Conversation History:\n{$historyText}\n\n";
        }

        $prompt .= "Current Question: {$query}\n\n";
        $prompt .= "Answer:";

        return $prompt;
    }

    /**
     * Format contexts for prompt
     */
    private function formatContexts(array $contexts): string
    {
        $formatted = [];

        foreach ($contexts as $index => $context) {
            $sourceInfo = "Source {$index + 1}";
            if (isset($context['document_id'])) {
                $sourceInfo .= " (Doc {$context['document_id']})";
            }

            $formatted[] = "[{$sourceInfo}]\n" . trim($context['content']) . "\n";
        }

        return implode("\n", $formatted);
    }

    /**
     * Format conversation history
     */
    private function formatConversationHistory(array $history): string
    {
        if (empty($history)) {
            return '';
        }

        $formatted = [];
        foreach ($history as $turn) {
            $formatted[] = "Human: " . $turn['query'];
            $formatted[] = "Assistant: " . $turn['response'];
        }

        return implode("\n", $formatted);
    }

    /**
     * Post-process generated response
     */
    private function postProcessResponse(string $response, array $contexts, array $options = []): array
    {
        // Extract citations
        $citations = $this->extractCitations($response, $contexts);

        // Calculate confidence score
        $confidence = $this->calculateConfidence($response, $contexts);

        // Clean up response text
        $cleanedResponse = $this->cleanupResponse($response);

        return [
            'text' => $cleanedResponse,
            'citations' => $citations,
            'confidence' => $confidence
        ];
    }

    /**
     * Extract citations from response
     */
    private function extractCitations(string $response, array $contexts): array
    {
        $citations = [];

        // Look for citation patterns like [Source 1], [1], etc.
        preg_match_all('/\[(?:Source\s+)?(\d+)\]/', $response, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $citationNumber) {
                $index = intval($citationNumber) - 1;
                if (isset($contexts[$index])) {
                    $citations[] = [
                        'index' => $citationNumber,
                        'content' => substr($contexts[$index]['content'], 0, 200) . '...',
                        'document_id' => $contexts[$index]['document_id'] ?? null,
                        'chunk_id' => $contexts[$index]['id'] ?? null,
                        'similarity' => $contexts[$index]['similarity'] ?? null
                    ];
                }
            }
        }

        return $citations;
    }

    /**
     * Calculate response confidence
     */
    private function calculateConfidence(string $response, array $contexts): float
    {
        $factors = [];

        // Factor 1: Context relevance (average similarity)
        $similarities = array_column($contexts, 'similarity');
        $avgSimilarity = !empty($similarities) ? array_sum($similarities) / count($similarities) : 0;
        $factors['context_relevance'] = $avgSimilarity;

        // Factor 2: Response length (reasonable length indicates thoroughness)
        $responseLength = strlen($response);
        $lengthScore = min(1.0, $responseLength / 500); // Normalize to 500 chars
        $factors['response_length'] = $lengthScore;

        // Factor 3: Citation coverage (how well the response cites sources)
        $citations = $this->extractCitations($response, $contexts);
        $citationScore = min(1.0, count($citations) / max(1, count($contexts)));
        $factors['citation_coverage'] = $citationScore;

        // Factor 4: Uncertainty indicators (presence of "I don't know", "unclear", etc.)
        $uncertaintyPhrases = ['i don\'t know', 'unclear', 'uncertain', 'not sure', 'cannot determine'];
        $uncertaintyPenalty = 0;
        foreach ($uncertaintyPhrases as $phrase) {
            if (stripos($response, $phrase) !== false) {
                $uncertaintyPenalty += 0.1;
            }
        }
        $factors['uncertainty'] = max(0, 1 - $uncertaintyPenalty);

        // Weighted average
        $weights = [
            'context_relevance' => 0.4,
            'response_length' => 0.2,
            'citation_coverage' => 0.3,
            'uncertainty' => 0.1
        ];

        $confidence = 0;
        foreach ($factors as $factor => $score) {
            $confidence += $weights[$factor] * $score;
        }

        return round($confidence, 3);
    }

    /**
     * Clean up response text
     */
    private function cleanupResponse(string $response): string
    {
        // Remove extra whitespace
        $response = preg_replace('/\s+/', ' ', $response);

        // Remove common artifacts
        $response = str_replace(['Answer:', 'Response:', 'Based on the context:'], '', $response);

        return trim($response);
    }

    /**
     * Extract source information
     */
    private function extractSources(array $contexts): array
    {
        $sources = [];

        foreach ($contexts as $context) {
            $sources[] = [
                'document_id' => $context['document_id'] ?? null,
                'chunk_id' => $context['id'] ?? null,
                'similarity' => $context['similarity'] ?? null,
                'content_preview' => substr($context['content'], 0, 100) . '...'
            ];
        }

        return $sources;
    }

    /**
     * Analyze query complexity
     */
    private function analyzeQueryComplexity(string $query): array
    {
        $wordCount = str_word_count($query);
        $questionMarks = substr_count($query, '?');
        $hasMultipleParts = strpos($query, ' and ') !== false || strpos($query, ' or ') !== false;

        if ($wordCount <= 5 && $questionMarks <= 1 && !$hasMultipleParts) {
            $type = 'simple';
        } elseif ($wordCount > 20 || $questionMarks > 1 || $hasMultipleParts) {
            $type = 'complex';
        } else {
            $type = 'moderate';
        }

        return [
            'type' => $type,
            'word_count' => $wordCount,
            'question_marks' => $questionMarks,
            'multiple_parts' => $hasMultipleParts
        ];
    }

    /**
     * Adapt generation parameters based on complexity
     */
    private function adaptGenerationParameters(array $complexity, array $options): array
    {
        $adapted = $options;

        switch ($complexity['type']) {
            case 'simple':
                $adapted['max_tokens'] = min($adapted['max_tokens'] ?? 512, 512);
                $adapted['temperature'] = 0.2;
                break;
            case 'complex':
                $adapted['max_tokens'] = max($adapted['max_tokens'] ?? 2048, 1024);
                $adapted['temperature'] = 0.4;
                $adapted['max_context_chunks'] = 15;
                break;
        }

        return $adapted;
    }

    /**
     * Generate simple response
     */
    private function generateSimple(string $query, array $contexts, array $options): array
    {
        $context = !empty($contexts) ? $contexts[0]['content'] : '';

        $prompt = "Based on this context: {$context}\n\nProvide a brief, direct answer to: {$query}\n\nAnswer:";

        $response = $this->vertexClient->generate($prompt, $options);

        return [
            'success' => true,
            'response' => trim($response),
            'citations' => [],
            'confidence' => 0.8,
            'sources' => $this->extractSources(array_slice($contexts, 0, 1)),
            'metadata' => [
                'generation_method' => 'simple'
            ]
        ];
    }

    /**
     * Generate complex response with enhanced reasoning
     */
    private function generateComplex(string $query, array $contexts, array $options): array
    {
        // Use chain-of-thought prompting for complex queries
        $prompt = $this->buildChainOfThoughtPrompt($query, $contexts, $options);

        $response = $this->vertexClient->generate($prompt, $options);
        $processedResponse = $this->postProcessResponse($response, $contexts, $options);

        return [
            'success' => true,
            'response' => $processedResponse['text'],
            'citations' => $processedResponse['citations'],
            'confidence' => $processedResponse['confidence'],
            'sources' => $this->extractSources($contexts),
            'metadata' => [
                'generation_method' => 'complex',
                'reasoning_steps' => $this->extractReasoningSteps($response)
            ]
        ];
    }

    /**
     * Build chain-of-thought prompt for complex queries
     */
    private function buildChainOfThoughtPrompt(string $query, array $contexts, array $options): string
    {
        $contextText = $this->formatContexts($contexts);

        $prompt = "You are an expert assistant. Think step-by-step to answer complex questions.\n\n";
        $prompt .= "Context Information:\n{$contextText}\n\n";
        $prompt .= "Question: {$query}\n\n";
        $prompt .= "Let's think through this step by step:\n";
        $prompt .= "1. First, I'll identify the key aspects of the question\n";
        $prompt .= "2. Then, I'll find relevant information in the context\n";
        $prompt .= "3. Finally, I'll synthesize a comprehensive answer\n\n";
        $prompt .= "Step-by-step reasoning and final answer:";

        return $prompt;
    }

    /**
     * Extract reasoning steps from complex responses
     */
    private function extractReasoningSteps(string $response): array
    {
        $steps = [];

        // Look for numbered steps or bullet points
        if (preg_match_all('/(?:Step \d+|^\d+\.|\*)\s*([^\\n]+)/mi', $response, $matches)) {
            $steps = $matches[1];
        }

        return $steps;
    }

    /**
     * Generate response in chunks (for streaming)
     */
    private function generateInChunks(string $prompt, array $options): array
    {
        // Simplified chunk generation
        // In a real implementation, you'd use streaming APIs

        $response = $this->vertexClient->generate($prompt, $options);
        $words = explode(' ', $response);

        $chunks = [];
        $chunkSize = 10; // words per chunk

        for ($i = 0; $i < count($words); $i += $chunkSize) {
            $chunk = implode(' ', array_slice($words, $i, $chunkSize));
            $chunks[] = $chunk . ' ';
        }

        return $chunks;
    }

    /**
     * Get fallback response for errors
     */
    private function getFallbackResponse(string $query): string
    {
        return "I apologize, but I encountered an error while processing your question: \"" .
               substr($query, 0, 100) . "...\". Please try rephrasing your question or contact support if the issue persists.";
    }

    /**
     * Get generation statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_generations' => $this->metrics->getTotalGenerations(),
            'average_latency' => $this->metrics->getAverageGenerationLatency(),
            'cache_hit_rate' => $this->cache->getHitRate('generation'),
            'error_rate' => $this->metrics->getErrorRate('generation'),
            'confidence_distribution' => $this->metrics->getConfidenceDistribution(),
            'strategies_used' => $this->metrics->getStrategiesUsed()
        ];
    }
}