<?php

namespace App\Service\AI;

use App\Service\AI\Providers\ClaudeService;
use App\Service\AI\Providers\TitanEmbeddingsService;
use App\Service\AI\Providers\BedrockClientFactory;
use Psr\Log\LoggerInterface;

/**
 * Main AI Service facade providing unified access to all AI capabilities
 */
class AIService
{
    public function __construct(
        private ClaudeService $claudeService,
        private TitanEmbeddingsService $embeddingsService,
        private BedrockClientFactory $clientFactory,
        private LoggerInterface $logger
    ) {}

    // ===== Claude Text Generation =====

    /**
     * Generate text response using Claude
     */
    public function generateText(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): array {
        return $this->claudeService->generateResponse($messages, $preferFastResponse, $maxTokens);
    }

    /**
     * Stream text response using Claude
     */
    public function streamText(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): \Generator {
        return $this->claudeService->streamResponse($messages, $preferFastResponse, $maxTokens);
    }

    // ===== Embeddings =====

    /**
     * Generate embedding for single text using Titan
     */
    public function generateEmbedding(string $text): array
    {
        return $this->embeddingsService->generateEmbedding($text);
    }

    /**
     * Generate embeddings for multiple texts using Titan
     */
    public function generateEmbeddings(array $texts): array
    {
        return $this->embeddingsService->generateEmbeddings($texts);
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    public function calculateCosineSimilarity(array $embedding1, array $embedding2): float
    {
        return $this->embeddingsService->calculateCosineSimilarity($embedding1, $embedding2);
    }

    // ===== Service Information =====

    /**
     * Get information about all AI services
     */
    public function getServicesInfo(): array
    {
        return [
            'claude' => $this->claudeService->getModelInfo(),
            'embeddings' => $this->embeddingsService->getModelInfo(),
            'bedrock_client' => $this->clientFactory->getClientInfo(),
            'capabilities' => [
                'text_generation' => true,
                'text_streaming' => true,
                'text_embeddings' => true,
                'image_generation' => false, // Future: Nova
                'multimodal' => false // Future: Nova
            ]
        ];
    }

    /**
     * Get specific service by name
     */
    public function getClaudeService(): ClaudeService
    {
        return $this->claudeService;
    }

    public function getEmbeddingsService(): TitanEmbeddingsService
    {
        return $this->embeddingsService;
    }

    // ===== Utility Methods =====


    /**
     * Health check for all AI services
     */
    public function healthCheck(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'services' => []
        ];

        // Test Claude service
        try {
            $testResponse = $this->claudeService->generateResponse([
                ['role' => 'user', 'content' => 'Say "OK" if you are working.']
            ], true, 10);
            
            $health['services']['claude'] = [
                'status' => 'healthy',
                'response_time' => null // Could add timing
            ];
        } catch (\Exception $e) {
            $health['services']['claude'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['overall_status'] = 'degraded';
        }

        // Test Titan embeddings
        try {
            $testEmbedding = $this->embeddingsService->generateEmbedding('test');
            
            $health['services']['embeddings'] = [
                'status' => 'healthy',
                'dimensions' => count($testEmbedding)
            ];
        } catch (\Exception $e) {
            $health['services']['embeddings'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['overall_status'] = 'degraded';
        }

        return $health;
    }

    // ===== Future Extensions =====
    
    // TODO: Add Nova image generation methods
    // public function generateImage(string $prompt, array $options = []): array
    // {
    //     return $this->novaService->generateImage($prompt, $options);
    // }

}