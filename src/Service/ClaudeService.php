<?php

namespace App\Service;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\CredentialProvider;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;

class ClaudeService
{
    private BedrockRuntimeClient $bedrockClient;

    public function __construct(
        private string $region,
        private string $sonnetModel,
        private string $haikuModel,
        private LoggerInterface $logger
    ) {
        // Detect if running in production/ECS environment
        $isProduction = getenv('APP_ENV') === 'prod' || 
                        getenv('AWS_EXECUTION_ENV') === 'AWS_ECS_FARGATE' ||
                        isset($_SERVER['AWS_EXECUTION_ENV']);
        
        if ($isProduction) {
            // Use default credential chain (IAM role in ECS)
            $this->bedrockClient = new BedrockRuntimeClient([
                'region' => $this->region,
                'version' => 'latest',
            ]);
        } else {
            // Use SSO for local development
            $profileName = getenv('AWS_PROFILE') ?: 'anny-prod';
            $credentialProvider = CredentialProvider::sso($profileName);
            
            $this->bedrockClient = new BedrockRuntimeClient([
                'region' => $this->region,
                'version' => 'latest',
                'credentials' => $credentialProvider,
            ]);
        }
    }

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
            $this->logger->error('Bedrock API error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
                'awsCode' => $e->getAwsErrorCode(),
            ]);
            
            throw new \RuntimeException('Failed to generate AI response: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Claude service error: ' . $e->getMessage(), [
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
            $this->logger->error('Bedrock streaming error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
                'awsCode' => $e->getAwsErrorCode(),
            ]);
            
            yield [
                'type' => 'error',
                'message' => 'Failed to stream AI response: ' . $e->getMessage(),
                'model' => $modelId
            ];
        } catch (\Exception $e) {
            $this->logger->error('Claude streaming error: ' . $e->getMessage(), [
                'model' => $modelId,
                'messages' => $messages,
            ]);
            
            yield [
                'type' => 'error',
                'message' => 'Failed to stream AI response: ' . $e->getMessage(),
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
