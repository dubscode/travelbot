<?php

namespace App\Service\AI\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TitanEmbeddingsService
{
    private const TITAN_MODEL_ID = 'amazon.titan-embed-text-v2:0';
    private const VECTOR_DIMENSIONS = 1024;
    private const CACHE_TTL = 86400; // 24 hours

    private BedrockRuntimeClient $bedrockClient;

    public function __construct(
        BedrockClientFactory $clientFactory,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
        $this->bedrockClient = $clientFactory->createClient();
    }

    /**
     * Generate embedding for a single text input
     */
    public function generateEmbedding(string $text): array
    {
        if (empty(trim($text))) {
            throw new \InvalidArgumentException('Text cannot be empty');
        }

        $cacheKey = 'titan_embedding_' . md5($text);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($text) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->callTitanEmbeddings($text);
        });
    }

    /**
     * Generate embeddings for multiple texts in batch
     */
    public function generateEmbeddings(array $texts): array
    {
        $embeddings = [];
        
        foreach ($texts as $index => $text) {
            try {
                $embeddings[$index] = $this->generateEmbedding($text);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to generate Titan embedding for text at index {index}', [
                    'index' => $index,
                    'text' => substr($text, 0, 100) . '...',
                    'error' => $e->getMessage()
                ]);
                $embeddings[$index] = null;
            }
        }

        return $embeddings;
    }

    /**
     * Call AWS Bedrock Titan embeddings model
     */
    private function callTitanEmbeddings(string $text): array
    {
        try {
            $requestBody = json_encode([
                'inputText' => $text,
                'dimensions' => self::VECTOR_DIMENSIONS,
                'normalize' => true
            ]);

            $this->logger->debug('Calling Titan embeddings model', [
                'model' => self::TITAN_MODEL_ID,
                'text_length' => strlen($text),
                'dimensions' => self::VECTOR_DIMENSIONS
            ]);

            $result = $this->bedrockClient->invokeModel([
                'modelId' => self::TITAN_MODEL_ID,
                'body' => $requestBody,
                'contentType' => 'application/json',
                'accept' => 'application/json'
            ]);

            $responseBody = json_decode($result['body']->getContents(), true);

            if (!isset($responseBody['embedding'])) {
                throw new \RuntimeException('No embedding found in Titan response');
            }

            $embedding = $responseBody['embedding'];

            // Validate embedding dimensions
            if (count($embedding) !== self::VECTOR_DIMENSIONS) {
                throw new \RuntimeException(sprintf(
                    'Expected %d dimensions, got %d',
                    self::VECTOR_DIMENSIONS,
                    count($embedding)
                ));
            }

            $this->logger->debug('Successfully generated Titan embedding', [
                'dimensions' => count($embedding),
                'input_tokens' => $responseBody['inputTextTokenCount'] ?? null
            ]);

            return $embedding;

        } catch (AwsException $e) {
            $this->logger->error('AWS Bedrock Titan error generating embedding', [
                'model' => self::TITAN_MODEL_ID,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_message' => $e->getAwsErrorMessage(),
                'text_preview' => substr($text, 0, 100)
            ]);
            
            throw new \RuntimeException('Failed to generate Titan embedding: ' . $e->getMessage(), 0, $e);
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error generating Titan embedding', [
                'model' => self::TITAN_MODEL_ID,
                'error' => $e->getMessage(),
                'text_preview' => substr($text, 0, 100)
            ]);
            
            throw new \RuntimeException('Failed to generate Titan embedding: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the model configuration
     */
    public function getModelInfo(): array
    {
        return [
            'model_id' => self::TITAN_MODEL_ID,
            'dimensions' => self::VECTOR_DIMENSIONS,
            'cache_ttl' => self::CACHE_TTL,
            'provider' => 'AWS Bedrock'
        ];
    }


    /**
     * Calculate cosine similarity between two embeddings
     */
    public function calculateCosineSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have the same dimensions');
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $magnitude1 += $embedding1[$i] * $embedding1[$i];
            $magnitude2 += $embedding2[$i] * $embedding2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0.0 || $magnitude2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}