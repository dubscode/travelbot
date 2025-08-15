<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ClaudeService
{
    private string $baseUrl;

    public function __construct(
        private string $region,
        private string $sonnetModel,
        private string $haikuModel,
        private LoggerInterface $logger,
        private string $bearerToken,
        private HttpClientInterface $httpClient
    ) {
        $this->baseUrl = "https://bedrock-runtime.{$this->region}.amazonaws.com";
    }

    public function generateResponse(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): array {
        // Choose model based on preference
        $modelId = $preferFastResponse ? $this->haikuModel : $this->sonnetModel;
        
        try {
            $url = "{$this->baseUrl}/model/{$modelId}/converse";
            
            $payload = [
                'messages' => $messages,
                'inferenceConfig' => [
                    'maxTokens' => $maxTokens,
                    'temperature' => 0.7,
                    'topP' => 0.9,
                ],
            ];

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->bearerToken,
                ],
                'json' => $payload,
            ]);

            $result = $response->toArray();
            
            return [
                'content' => $result['output']['message']['content'][0]['text'] ?? '',
                'model' => $modelId,
                'usage' => $result['usage'] ?? null,
                'stopReason' => $result['stopReason'] ?? null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Claude API error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
            ]);
            
            throw new \RuntimeException('Failed to generate AI response: ' . $e->getMessage(), 0, $e);
        }
    }

    public function streamResponse(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): \Generator {
        $modelId = $preferFastResponse ? $this->haikuModel : $this->sonnetModel;
        
        // For now, fall back to non-streaming since streaming with HTTP client is more complex
        // TODO: Implement proper streaming with Server-Sent Events
        try {
            $response = $this->generateResponse($messages, $preferFastResponse, $maxTokens);
            
            // Simulate streaming by yielding the full response
            yield [
                'type' => 'content',
                'text' => $response['content'],
                'model' => $response['model']
            ];
            
            yield [
                'type' => 'stop',
                'stopReason' => $response['stopReason'] ?? 'end_turn',
                'model' => $response['model']
            ];

        } catch (\Exception $e) {
            $this->logger->error('Claude streaming error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
            ]);
            
            yield [
                'type' => 'error',
                'message' => 'Failed to generate AI response: ' . $e->getMessage(),
                'model' => $modelId
            ];
        }
    }

    public function getSonnetModel(): string
    {
        return $this->sonnetModel;
    }

    public function getHaikuModel(): string
    {
        return $this->haikuModel;
    }
}
