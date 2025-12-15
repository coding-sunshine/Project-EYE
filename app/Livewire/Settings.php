<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Setting;
use App\Services\AiService;

class Settings extends Component
{
    // Captioning Models
    public $captioning_model;
    public $available_captioning_models = [
        'florence' => 'Florence-2 (Recommended - Best Quality, Multi-task)',
        'blip' => 'BLIP Large (Fast, Good Quality)',
    ];

    // Embedding Models
    public $embedding_model;
    public $available_embedding_models = [
        'aimv2' => 'AIMv2 (Recommended - Apple\'s model, outperforms SigLIP)',
        'siglip' => 'SigLIP (Google - Good accuracy for search)',
        'clip' => 'CLIP (OpenAI - Fast, legacy option)',
    ];

    // Face Detection
    public $face_detection_enabled;

    // Ollama
    public $ollama_enabled;
    public $ollama_model;
    public $ollama_model_document;
    public $available_ollama_models = [
        // Recommended for Apple Silicon (16GB)
        'qwen2.5:7b' => 'Qwen 2.5 7B (Recommended - Best Reasoning)',
        'llava:13b-v1.6' => 'LLaVA 1.6 13B (Best Vision Quality)',
        'minicpm-v:8b' => 'MiniCPM-V 8B (Fast Vision, Efficient)',
        'llama3.2:latest' => 'Llama 3.2 (Good General Purpose)',
        // Legacy options
        'llava' => 'LLaVA 7B (Legacy Vision)',
        'mistral' => 'Mistral 7B (Fast, Text Only)',
    ];

    public $available_ollama_document_models = [
        'qwen2.5:7b' => 'Qwen 2.5 7B (Recommended - Best for Documents)',
        'llama3.2:latest' => 'Llama 3.2 (Good Alternative)',
        'mistral' => 'Mistral 7B (Fast)',
    ];

    // OCR Engine
    public $ocr_engine;
    public $available_ocr_engines = [
        'auto' => 'Auto (PaddleOCR preferred, Tesseract fallback)',
        'paddleocr' => 'PaddleOCR (Best accuracy, complex layouts)',
        'tesseract' => 'Tesseract (Faster, simple documents)',
    ];

    // Status
    public $saved = false;
    public $error = null;
    public $ai_service_status = null;
    public $model_status = [];
    public $preloading = false;

    public function mount()
    {
        $this->loadSettings();
        $this->checkAiServiceStatus();
        $this->loadModelStatus();
    }

    public function loadSettings()
    {
        $this->captioning_model = Setting::get('captioning_model', 'florence');
        $this->embedding_model = Setting::get('embedding_model', 'aimv2');
        
        // Load boolean settings and ensure they are actual booleans
        $faceDetection = Setting::get('face_detection_enabled', true);
        $this->face_detection_enabled = is_bool($faceDetection) ? $faceDetection : ($faceDetection === 'true' || $faceDetection === true);
        
        $ollamaEnabled = Setting::get('ollama_enabled', true);
        $this->ollama_enabled = is_bool($ollamaEnabled) ? $ollamaEnabled : ($ollamaEnabled === 'true' || $ollamaEnabled === true);

        $this->ollama_model = Setting::get('ollama_model', 'llava:13b-v1.6');
        $this->ollama_model_document = Setting::get('ollama_model_document', 'qwen2.5:7b');

        // OCR Engine
        $this->ocr_engine = Setting::get('ocr_engine', 'auto');
    }

    public function checkAiServiceStatus()
    {
        try {
            $aiService = app(AiService::class);
            $this->ai_service_status = $aiService->isHealthy() ? 'online' : 'offline';
        } catch (\Exception $e) {
            $this->ai_service_status = 'error';
            $this->error = $e->getMessage();
        }
    }

    public function save()
    {
        try {
            // Save all settings - the Setting model handles JSON encoding
            Setting::set('captioning_model', $this->captioning_model);
            Setting::set('embedding_model', $this->embedding_model);
            Setting::set('face_detection_enabled', $this->face_detection_enabled); // Save as boolean
            Setting::set('ollama_enabled', $this->ollama_enabled); // Save as boolean
            Setting::set('ollama_model', $this->ollama_model);
            Setting::set('ollama_model_document', $this->ollama_model_document);
            Setting::set('ocr_engine', $this->ocr_engine);

            $this->saved = true;
            $this->error = null;

            // Reset saved message after 3 seconds
            $this->dispatch('setting-saved');
        } catch (\Exception $e) {
            $this->error = 'Failed to save settings: ' . $e->getMessage();
            $this->saved = false;
        }
    }

    public function loadModelStatus()
    {
        try {
            $aiService = app(AiService::class);
            $this->model_status = $aiService->getModelStatus();
        } catch (\Exception $e) {
            $this->model_status = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    public function testConnection()
    {
        $this->checkAiServiceStatus();
        $this->loadModelStatus();
    }
    
    public function preloadModels()
    {
        $this->preloading = true;
        
        try {
            $aiService = app(AiService::class);
            $success = $aiService->preloadModels();
            
            if ($success) {
                $this->saved = true;
                $this->error = null;
                $this->dispatch('models-preloaded');
            } else {
                $this->error = 'Failed to preload models. Check if AI service is running.';
            }
        } catch (\Exception $e) {
            $this->error = 'Error preloading models: ' . $e->getMessage();
        }
        
        $this->preloading = false;
        $this->loadModelStatus();
    }

    public function render()
    {
        return view('livewire.settings')
            ->layout('layouts.app');
    }
}

