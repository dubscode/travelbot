<?php

namespace App\Service\AI\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;

class ClaudeService
{
    private BedrockRuntimeClient $bedrockClient;

    public function __construct(
        BedrockClientFactory $clientFactory,
        private string $sonnetModel,
        private string $haikuModel,
        private LoggerInterface $logger
    ) {
        $this->bedrockClient = $clientFactory->createClient();
    }

    /**
     * Generate a response using Claude models
     */
    public function generateResponse(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): array {
        // Choose model based on preference
        $modelId = $preferFastResponse ? $this->haikuModel : $this->sonnetModel;
        
        try {
            $result = $this->bedrockClient->converse([
                'modelId' => $modelId,
                'messages' => $messages,
                'inferenceConfig' => [
                    'maxTokens' => $maxTokens,
                    'temperature' => 0.7,
                    'topP' => 0.9,
                ],
            ]);
            
            return [
                'content' => $result['output']['message']['content'][0]['text'] ?? '',
                'model' => $modelId,
                'usage' => $result['usage'] ?? null,
                'stopReason' => $result['stopReason'] ?? null,
            ];

        } catch (AwsException $e) {
            $this->logger->error('Claude API error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
                'awsCode' => $e->getAwsErrorCode(),
            ]);
            
            throw new \RuntimeException('Failed to generate Claude response: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Claude service error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
            ]);
            
            throw new \RuntimeException('Failed to generate Claude response: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Stream a response using Claude models
     */
    public function streamResponse(
        array $messages,
        bool $preferFastResponse = false,
        int $maxTokens = 4000
    ): \Generator {
        $modelId = $preferFastResponse ? $this->haikuModel : $this->sonnetModel;
        
        try {
            $result = $this->bedrockClient->converseStream([
                'modelId' => $modelId,
                'messages' => $messages,
                'inferenceConfig' => [
                    'maxTokens' => $maxTokens,
                    'temperature' => 0.7,
                    'topP' => 0.9,
                ],
            ]);

            // Process the streaming response
            foreach ($result['stream'] as $event) {
                if (isset($event['contentBlockDelta'])) {
                    // Content token
                    $delta = $event['contentBlockDelta'];
                    if (isset($delta['delta']['text'])) {
                        $tokenText = $delta['delta']['text'];
                        
                        yield [
                            'type' => 'content',
                            'text' => $tokenText,
                            'model' => $modelId
                        ];
                    }
                } elseif (isset($event['messageStop'])) {
                    // End of message
                    $stopReason = $event['messageStop']['stopReason'] ?? 'end_turn';
                    
                    yield [
                        'type' => 'stop',
                        'stopReason' => $stopReason,
                        'model' => $modelId
                    ];
                    break;
                } elseif (isset($event['metadata'])) {
                    // Usage statistics
                    yield [
                        'type' => 'metadata',
                        'usage' => $event['metadata']['usage'] ?? null,
                        'model' => $modelId
                    ];
                }
            }

        } catch (AwsException $e) {
            $this->logger->error('Claude streaming error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
                'awsCode' => $e->getAwsErrorCode(),
            ]);
            
            yield [
                'type' => 'error',
                'message' => 'Failed to stream Claude response: ' . $e->getMessage(),
                'model' => $modelId
            ];
        } catch (\Exception $e) {
            $this->logger->error('Claude streaming error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
            ]);
            
            yield [
                'type' => 'error',
                'message' => 'Failed to stream Claude response: ' . $e->getMessage(),
                'model' => $modelId
            ];
        }
    }

    /**
     * Get the Sonnet model ID
     */
    public function getSonnetModel(): string
    {
        return $this->sonnetModel;
    }

    /**
     * Get the Haiku model ID
     */
    public function getHaikuModel(): string
    {
        return $this->haikuModel;
    }

    /**
     * Get model information
     */
    public function getModelInfo(): array
    {
        return [
            'sonnet_model' => $this->sonnetModel,
            'haiku_model' => $this->haikuModel,
            'provider' => 'AWS Bedrock'
        ];
    }
}