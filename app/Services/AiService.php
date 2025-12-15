<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use Exception;

class AiService
{
    /**
     * Base URL for the AI service.
     */
    protected string $baseUrl;

    /**
     * Default timeout for API requests in seconds.
     * Use getTimeoutFor() for operation-specific timeouts.
     */
    protected int $defaultTimeout;

    /**
     * File service for path conversions.
     */
    protected FileService $fileService;

    /**
     * Cache service for AI analysis results.
     */
    protected CacheService $cacheService;

    /**
     * Circuit breaker for AI service protection.
     */
    protected CircuitBreakerService $circuitBreaker;

    /**
     * Retry service for handling transient failures.
     */
    protected RetryService $retryService;

    /**
     * Create a new AiService instance.
     */
    public function __construct(FileService $fileService, CacheService $cacheService)
    {
        $this->baseUrl = config('ai.api_url');
        $this->defaultTimeout = config('ai.timeout', 120);
        $this->fileService = $fileService;
        $this->cacheService = $cacheService;

        // Initialize circuit breaker with configurable settings
        $this->circuitBreaker = new CircuitBreakerService(
            serviceName: 'ai_service',
            failureThreshold: config('ai.circuit_breaker.failure_threshold', 5),
            recoveryTimeout: config('ai.circuit_breaker.recovery_timeout', 60)
        );

        // Initialize retry service with configurable settings
        $this->retryService = new RetryService(
            maxAttempts: config('ai.retry.max_attempts', 3),
            initialDelayMs: config('ai.retry.initial_delay_ms', 100),
            maxDelayMs: config('ai.retry.max_delay_ms', 10000),
            multiplier: config('ai.retry.multiplier', 2.0),
            useJitter: config('ai.retry.use_jitter', true),
            operationName: 'ai_service'
        );
    }

    /**
     * Get adaptive timeout for specific operation type.
     *
     * @param string $operation Operation type: 'image', 'video', 'document', 'audio', 'embedding', 'health'
     * @param bool $ollamaEnabled Whether Ollama processing is enabled
     * @return int Timeout in seconds
     */
    protected function getTimeoutFor(string $operation, bool $ollamaEnabled = false): int
    {
        $timeouts = config('ai.timeouts', []);

        // For operations with Ollama support
        if (in_array($operation, ['image', 'video', 'document', 'audio'])) {
            $operationTimeouts = $timeouts[$operation] ?? [];

            if ($ollamaEnabled && isset($operationTimeouts['ollama'])) {
                return $operationTimeouts['ollama'];
            }

            return $operationTimeouts['standard'] ?? $timeouts['default'] ?? $this->defaultTimeout;
        }

        // For simple operations (embedding, health, etc.)
        return $timeouts[$operation] ?? $timeouts['default'] ?? $this->defaultTimeout;
    }

    /**
     * Check if the AI service is healthy and ready.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $timeout = $this->getTimeoutFor('health');
            $response = Http::timeout($timeout)->get($this->baseUrl . config('ai.endpoints.health'));
            
            if ($response->successful()) {
                $data = $response->json();
                return isset($data['models_loaded']) && $data['models_loaded'] === true;
            }
            
            return false;
        } catch (Exception $e) {
            Log::error('AI service health check failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the status of AI models including download progress.
     *
     * @return array
     */
    public function getModelStatus(): array
    {
        try {
            $timeout = $this->getTimeoutFor('health');
            $response = Http::timeout($timeout)->get($this->baseUrl . config('ai.endpoints.health'));

            if ($response->successful()) {
                $data = $response->json();

                // Map health endpoint response to expected format
                $isHealthy = isset($data['models_loaded']) && $data['models_loaded'] === true;

                return [
                    'status' => $isHealthy ? 'online' : 'offline',
                    'loaded_models' => $data['features'] ?? [],
                    'ollama_available' => $data['features']['ollama'] ?? false,
                    'face_recognition_available' => isset($data['features']) &&
                        (in_array('face_recognition', $data['features']) ||
                         in_array('face_detection', $data['features'])),
                    'device' => $data['device'] ?? 'unknown',
                    'models' => [],  // For backward compatibility
                    'downloading' => []  // For backward compatibility
                ];
            }

            return [
                'status' => 'offline',
                'loaded_models' => [],
                'ollama_available' => false,
                'face_recognition_available' => false,
                'models' => [],
                'downloading' => []
            ];
        } catch (Exception $e) {
            Log::error('Failed to get model status: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'loaded_models' => [],
                'ollama_available' => false,
                'face_recognition_available' => false,
                'models' => [],
                'downloading' => []
            ];
        }
    }
    
    /**
     * Trigger model preload for configured models.
     *
     * @return bool
     */
    public function preloadModels(): bool
    {
        try {
            $captioningModel = Setting::get('captioning_model', 'florence');
            $embeddingModel = Setting::get('embedding_model', 'aimv2');

            $timeout = $this->getTimeoutFor('preload');
            $response = Http::timeout($timeout)->post($this->baseUrl . '/api/preload-models', [
                'captioning_model' => $captioningModel,
                'embedding_model' => $embeddingModel,
            ]);
            
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to preload models: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Analyze an image and get detailed description and embedding.
     *
     * @param string $imagePath Full path to the image file
     * @return array{description: string, embedding: array}
     * @throws Exception
     */
    public function analyzeImage(string $imagePath): array
    {
        // Check cache first
        $cached = $this->cacheService->get($imagePath);
        if ($cached !== null) {
            Log::info('Using cached image analysis result', ['path' => $imagePath]);
            return $cached;
        }

        try {
            // Convert Laravel storage path to shared volume path using FileService
            $sharedPath = $this->fileService->convertToSharedPath($imagePath);
            
            // Get model settings
            $captioningModel = Setting::get('captioning_model', 'florence');
            $embeddingModel = Setting::get('embedding_model', 'aimv2');

            // Handle boolean settings (could be boolean or string)
            $faceDetectionRaw = Setting::get('face_detection_enabled', true);
            $faceDetectionEnabled = is_bool($faceDetectionRaw) ? $faceDetectionRaw : ($faceDetectionRaw === 'true' || $faceDetectionRaw === true);

            $ollamaEnabledRaw = Setting::get('ollama_enabled', false);
            $ollamaEnabled = is_bool($ollamaEnabledRaw) ? $ollamaEnabledRaw : ($ollamaEnabledRaw === 'true' || $ollamaEnabledRaw === true);

            $ollamaModel = Setting::get('ollama_model', 'llava:13b-v1.6');

            // Get adaptive timeout based on whether Ollama is enabled
            $timeout = $this->getTimeoutFor('image', $ollamaEnabled);

            Log::info('Analyzing image via AI service', [
                'original_path' => $imagePath,
                'shared_path' => $sharedPath,
                'captioning_model' => $captioningModel,
                'embedding_model' => $embeddingModel,
                'face_detection' => $faceDetectionEnabled,
                'ollama_enabled' => $ollamaEnabled,
                'ollama_model' => $ollamaModel
            ]);

            $requestData = [
                'image_path' => $sharedPath,
                'captioning_model' => $captioningModel,
                'embedding_model' => $embeddingModel,
                'detect_faces' => $faceDetectionEnabled,
                'use_ollama' => $ollamaEnabled,
                'ollama_model' => $ollamaModel,
            ];

            // Wrap HTTP request with retry and circuit breaker protection
            $data = $this->retryService->execute(function () use ($timeout, $requestData) {
                return $this->circuitBreaker->execute(function () use ($timeout, $requestData) {
                    $response = Http::timeout($timeout)
                        ->post($this->baseUrl . config('ai.endpoints.analyze'), $requestData);

                    if (!$response->successful()) {
                        throw new Exception('AI service returned error: ' . $response->body());
                    }

                    return $response->json();
                });
            });

            if (!isset($data['description'])) {
                throw new Exception('Invalid response from AI service: missing description.');
            }

            // Embedding is optional for non-raster files (SVG, code files, etc.)
            if (!isset($data['embedding']) && !array_key_exists('embedding', $data)) {
                throw new Exception('Invalid response from AI service: embedding field missing.');
            }

            Log::info('Image analysis completed', [
                'description_length' => strlen($data['description']),
                'detailed_description_length' => isset($data['detailed_description']) ? strlen($data['detailed_description']) : 0,
                'meta_tags_count' => isset($data['meta_tags']) ? count($data['meta_tags']) : 0,
                'face_count' => $data['face_count'] ?? 0,
                'embedding_size' => is_array($data['embedding']) ? count($data['embedding']) : 0,
                'has_embedding' => isset($data['embedding']) && $data['embedding'] !== null
            ]);

            // Convert Python thumbnail path to Laravel storage path
            $thumbnail_path = null;
            if (isset($data['thumbnail_path'])) {
                // Convert /app/shared/images/thumbnails/abc.jpg to public/images/thumbnails/abc.jpg
                $thumbnail_path = str_replace('/app/shared/', 'public/', $data['thumbnail_path']);
            }

            $result = [
                'description' => $data['description'],
                'detailed_description' => $data['detailed_description'] ?? null,
                'meta_tags' => $data['meta_tags'] ?? [],
                'embedding' => $data['embedding'] ?? null,  // Allow null for non-raster files (SVG, code, etc.)
                'face_count' => $data['face_count'] ?? 0,
                'face_encodings' => $data['face_encodings'] ?? [],  // Legacy support
                'faces' => $data['faces'] ?? [],  // New: detailed face data with locations
                'thumbnail_path' => $thumbnail_path,
                'extracted_text' => $data['extracted_text'] ?? '',  // For SVG and other text-based formats
                // Maximum analysis coverage fields
                'objects_detected' => $data['objects_detected'] ?? null,
                'scene_classification' => $data['scene_classification'] ?? null,
                'dominant_colors' => $data['dominant_colors'] ?? null,
                'image_quality' => $data['image_quality'] ?? null,
                'quality_tier' => $data['quality_tier'] ?? null,
                'phash' => $data['phash'] ?? null,
                'dhash' => $data['dhash'] ?? null,
            ];

            // Cache the result for 24 hours
            $this->cacheService->put($imagePath, $result);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to analyze image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate embedding for text query.
     *
     * @param string $query Text query
     * @return array The embedding vector
     * @throws Exception
     */
    public function embedText(string $query): array
    {
        try {
            // Get embedding model setting
            $embeddingModel = Setting::get('embedding_model', 'aimv2');

            // Get adaptive timeout for embedding
            $timeout = $this->getTimeoutFor('embedding');

            Log::info('Generating text embedding', [
                'query' => $query,
                'embedding_model' => $embeddingModel
            ]);

            // Wrap HTTP request with retry and circuit breaker protection
            $data = $this->retryService->execute(function () use ($timeout, $query, $embeddingModel) {
                return $this->circuitBreaker->execute(function () use ($timeout, $query, $embeddingModel) {
                    $response = Http::timeout($timeout)
                        ->post($this->baseUrl . config('ai.endpoints.embed_text'), [
                            'query' => $query,
                            'embedding_model' => $embeddingModel,
                        ]);

                    if (!$response->successful()) {
                        throw new Exception('AI service returned error: ' . $response->body());
                    }

                    return $response->json();
                });
            });

            if (!isset($data['embedding'])) {
                throw new Exception('Invalid response from AI service');
            }

            Log::info('Text embedding generated', [
                'embedding_size' => count($data['embedding']),
                'model_used' => $data['model_used'] ?? $embeddingModel
            ]);

            return $data['embedding'];
        } catch (Exception $e) {
            Log::error('Failed to generate text embedding', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze a video file and extract scene descriptions.
     *
     * @param string $videoPath Full path to the video file
     * @param bool $extractFrames Whether to extract frames for analysis
     * @param int $frameInterval Extract 1 frame every N frames
     * @return array
     * @throws Exception
     */
    public function analyzeVideo(string $videoPath, bool $extractFrames = true, int $frameInterval = 30): array
    {
        // Check cache first
        $cached = $this->cacheService->get($videoPath);
        if ($cached !== null) {
            Log::info('Using cached video analysis result', ['path' => $videoPath]);
            return $cached;
        }

        try {
            $sharedPath = $this->fileService->convertToSharedPath($videoPath);

            // Get adaptive timeout for video (currently no Ollama support, but configurable for future)
            $timeout = $this->getTimeoutFor('video', false);

            Log::info('Analyzing video via AI service', [
                'original_path' => $videoPath,
                'shared_path' => $sharedPath,
            ]);

            // Wrap HTTP request with retry and circuit breaker protection
            $data = $this->retryService->execute(function () use ($timeout, $sharedPath, $extractFrames, $frameInterval) {
                return $this->circuitBreaker->execute(function () use ($timeout, $sharedPath, $extractFrames, $frameInterval) {
                    $response = Http::timeout($timeout)
                        ->post($this->baseUrl . '/analyze-video', [
                            'video_path' => $sharedPath,
                            'extract_frames' => $extractFrames,
                            'frame_interval' => $frameInterval,
                        ]);

                    if (!$response->successful()) {
                        throw new Exception('AI service returned error: ' . $response->body());
                    }

                    return $response->json();
                });
            });

            Log::info('Video analysis completed', [
                'scene_count' => count($data['scene_descriptions'] ?? []),
                'duration' => $data['duration_seconds'] ?? 0,
            ]);

            // Convert Python thumbnail path to Laravel storage path
            $thumbnail_path = null;
            if (isset($data['thumbnail_path'])) {
                // Convert /app/shared/videos/thumbnails/abc.jpg to public/videos/thumbnails/abc.jpg
                $thumbnail_path = str_replace('/app/shared/', 'public/', $data['thumbnail_path']);
            }

            $result = array_merge($data, [
                'thumbnail_path' => $thumbnail_path
            ]);

            // Cache the result for 24 hours
            $this->cacheService->put($videoPath, $result);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to analyze video', [
                'path' => $videoPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze a document and extract text via OCR.
     *
     * @param string $documentPath Full path to the document file
     * @param bool $performOcr Whether to perform OCR
     * @return array
     * @throws Exception
     */
    public function analyzeDocument(string $documentPath, bool $performOcr = true): array
    {
        // Check cache first
        $cached = $this->cacheService->get($documentPath);
        if ($cached !== null) {
            Log::info('Using cached document analysis result', ['path' => $documentPath]);
            return $cached;
        }

        try {
            $sharedPath = $this->fileService->convertToSharedPath($documentPath);

            // Get Ollama settings for document analysis
            $ollamaEnabledRaw = Setting::get('ollama_enabled', false);
            $ollamaEnabled = is_bool($ollamaEnabledRaw) ? $ollamaEnabledRaw : ($ollamaEnabledRaw === 'true' || $ollamaEnabledRaw === true);

            $ollamaModel = Setting::get('ollama_model_document', Setting::get('ollama_model', 'llama3.2'));

            // Get OCR engine setting (auto, paddleocr, tesseract)
            $ocrEngine = Setting::get('ocr_engine', 'auto');

            // Get adaptive timeout based on whether Ollama is enabled
            $timeout = $this->getTimeoutFor('document', $ollamaEnabled);

            Log::info('Analyzing document via AI service', [
                'original_path' => $documentPath,
                'shared_path' => $sharedPath,
                'perform_ocr' => $performOcr,
                'ocr_engine' => $ocrEngine,
                'ollama_enabled' => $ollamaEnabled,
                'ollama_model' => $ollamaModel,
            ]);

            $requestData = [
                'document_path' => $sharedPath,
                'perform_ocr' => $performOcr,
                'ocr_engine' => $ocrEngine,
            ];

            // Add Ollama settings
            if ($ollamaEnabled) {
                $requestData['use_ollama'] = true;
                $requestData['ollama_model'] = $ollamaModel;
            }

            // Wrap HTTP request with retry and circuit breaker protection
            $data = $this->retryService->execute(function () use ($timeout, $requestData) {
                return $this->circuitBreaker->execute(function () use ($timeout, $requestData) {
                    $response = Http::timeout($timeout)
                        ->post($this->baseUrl . '/analyze-document', $requestData);

                    if (!$response->successful()) {
                        throw new Exception('AI service returned error: ' . $response->body());
                    }

                    return $response->json();
                });
            });

            Log::info('Document analysis completed', [
                'text_length' => strlen($data['extracted_text'] ?? ''),
                'keywords_count' => count($data['keywords'] ?? []),
                'document_type' => $data['document_type'] ?? null,
                'has_summary' => isset($data['summary']),
            ]);

            // Convert Python thumbnail path to Laravel storage path
            $thumbnail_path = null;
            if (isset($data['thumbnail_path'])) {
                // Convert /app/shared/documents/thumbnails/abc.jpg to public/documents/thumbnails/abc.jpg
                $thumbnail_path = str_replace('/app/shared/', 'public/', $data['thumbnail_path']);
            }

            $result = array_merge($data, [
                'thumbnail_path' => $thumbnail_path
            ]);

            // Cache the result for 24 hours
            $this->cacheService->put($documentPath, $result);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to analyze document', [
                'path' => $documentPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Transcribe an audio file to text.
     *
     * @param string $audioPath Full path to the audio file
     * @param string|null $language Language code (e.g., 'en', 'es') or null for auto-detect
     * @return array
     * @throws Exception
     */
    public function transcribeAudio(string $audioPath, ?string $language = null): array
    {
        // Check cache first
        $cached = $this->cacheService->get($audioPath);
        if ($cached !== null) {
            Log::info('Using cached audio transcription result', ['path' => $audioPath]);
            return $cached;
        }

        try {
            $sharedPath = $this->fileService->convertToSharedPath($audioPath);

            // Get adaptive timeout for audio transcription (currently no Ollama support, but configurable for future)
            $timeout = $this->getTimeoutFor('audio', false);

            Log::info('Transcribing audio via AI service', [
                'original_path' => $audioPath,
                'shared_path' => $sharedPath,
                'language' => $language ?? 'auto',
            ]);

            // Wrap HTTP request with retry and circuit breaker protection
            $data = $this->retryService->execute(function () use ($timeout, $sharedPath, $language) {
                return $this->circuitBreaker->execute(function () use ($timeout, $sharedPath, $language) {
                    $response = Http::timeout($timeout)
                        ->post($this->baseUrl . '/transcribe-audio', [
                            'audio_path' => $sharedPath,
                            'language' => $language,
                        ]);

                    if (!$response->successful()) {
                        throw new Exception('AI service returned error: ' . $response->body());
                    }

                    return $response->json();
                });
            });

            Log::info('Audio transcription completed', [
                'text_length' => strlen($data['text'] ?? ''),
                'language' => $data['language'] ?? 'unknown',
            ]);

            // Convert Python thumbnail path to Laravel storage path (for future audio waveform support)
            $thumbnail_path = null;
            if (isset($data['thumbnail_path'])) {
                // Convert /app/shared/audio/thumbnails/abc.jpg to public/audio/thumbnails/abc.jpg
                $thumbnail_path = str_replace('/app/shared/', 'public/', $data['thumbnail_path']);
            }

            $result = array_merge($data, [
                'thumbnail_path' => $thumbnail_path
            ]);

            // Cache the result for 24 hours
            $this->cacheService->put($audioPath, $result);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to transcribe audio', [
                'path' => $audioPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

