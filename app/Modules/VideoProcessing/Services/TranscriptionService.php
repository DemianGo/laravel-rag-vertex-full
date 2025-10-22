<?php

namespace App\Modules\VideoProcessing\Services;

use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    private string $projectId;
    private string $location;
    private string $languageCode;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->projectId = config('video_processing.vertex_ai.project_id');
        $this->location = config('video_processing.vertex_ai.location', 'us-central1');
        $this->languageCode = config('video_processing.vertex_ai.language_code', 'pt-BR');
        $this->model = config('video_processing.vertex_ai.model', 'latest_long');
        $this->timeout = config('video_processing.vertex_ai.timeout', 1800);
    }

    /**
     * Transcribe audio using Vertex AI Speech-to-Text
     */
    public function transcribe(string $audioUrl): string
    {
        try {
            $client = new SpeechClient();
            
            $config = new RecognitionConfig([
                'encoding' => AudioEncoding::MP3,
                'sample_rate_hertz' => 44100,
                'language_code' => $this->languageCode,
                'model' => $this->model,
                'enable_automatic_punctuation' => true,
                'enable_word_time_offsets' => true,
            ]);

            $audio = new RecognitionAudio([
                'uri' => $audioUrl,
            ]);

            $operation = $client->longRunningRecognize($config, $audio);
            
            Log::info('Transcription operation started', [
                'operation_name' => $operation->getName(),
                'audio_url' => $audioUrl,
            ]);

            // Poll for completion
            $result = $operation->pollUntilComplete([
                'initialPollDelayMillis' => 1000,
                'maxPollDelayMillis' => 10000,
                'totalPollTimeoutMillis' => $this->timeout * 1000,
            ]);

            $transcript = '';
            foreach ($result->getResults() as $result) {
                $transcript .= $result->getAlternatives()[0]->getTranscript() . ' ';
            }

            $client->close();
            
            Log::info('Transcription completed', [
                'operation_name' => $operation->getName(),
                'transcript_length' => strlen($transcript),
            ]);

            return trim($transcript);
        } catch (\Exception $e) {
            Log::error('Transcription failed', [
                'audio_url' => $audioUrl,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Poll transcription status (for async operations)
     */
    public function pollTranscriptionStatus(string $operationName): array
    {
        try {
            $client = new SpeechClient();
            $operation = $client->getOperation($operationName);
            
            $status = [
                'done' => $operation->getDone(),
                'name' => $operation->getName(),
            ];

            if ($operation->getDone()) {
                if ($operation->getError()) {
                    $status['error'] = $operation->getError()->getMessage();
                } else {
                    $result = $operation->getResponse();
                    $status['result'] = $result;
                }
            }

            $client->close();
            return $status;
        } catch (\Exception $e) {
            Log::error('Transcription status polling failed', [
                'operation_name' => $operationName,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Save transcription to storage
     */
    public function saveTranscription(string $content, string $path): bool
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk(config('video_processing.storage.disk'));
            $success = $disk->put($path, $content);
            
            if ($success) {
                Log::info('Transcription saved to storage', [
                    'path' => $path,
                    'content_length' => strlen($content),
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error('Transcription save failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}

