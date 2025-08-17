<?php

namespace App\Service\AI\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\CredentialProvider;
use Psr\Log\LoggerInterface;

class BedrockClientFactory
{
    private ?BedrockRuntimeClient $client = null;

    public function __construct(
        private string $region,
        private LoggerInterface $logger
    ) {}

    /**
     * Create or return existing Bedrock Runtime Client
     */
    public function createClient(): BedrockRuntimeClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        // Detect if running in production/ECS environment
        $isProduction = getenv('APP_ENV') === 'prod' || 
                        getenv('AWS_EXECUTION_ENV') === 'AWS_ECS_FARGATE' ||
                        isset($_SERVER['AWS_EXECUTION_ENV']);
        
        if ($isProduction) {
            // Use default credential chain (IAM role in ECS)
            $this->client = new BedrockRuntimeClient([
                'region' => $this->region,
                'version' => 'latest',
            ]);
            
            $this->logger->info('BedrockClientFactory: Created client with IAM role credentials', [
                'region' => $this->region,
                'environment' => 'production'
            ]);
        } else {
            // Use SSO for local development
            $profileName = getenv('AWS_PROFILE') ?: 'anny-prod';
            $credentialProvider = CredentialProvider::sso($profileName);
            
            $this->client = new BedrockRuntimeClient([
                'region' => $this->region,
                'version' => 'latest',
                'credentials' => $credentialProvider,
            ]);
            
            $this->logger->info('BedrockClientFactory: Created client with SSO credentials', [
                'region' => $this->region,
                'profile' => $profileName,
                'environment' => 'development'
            ]);
        }

        return $this->client;
    }

    /**
     * Get client configuration information
     */
    public function getClientInfo(): array
    {
        $client = $this->createClient();
        
        return [
            'region' => $this->region,
            'api_version' => $client->getApi()->getApiVersion(),
            'service_name' => $client->getApi()->getServiceName(),
            'environment' => getenv('APP_ENV') === 'prod' ? 'production' : 'development'
        ];
    }

    /**
     * Reset the client (useful for testing)
     */
    public function resetClient(): void
    {
        $this->client = null;
    }
}