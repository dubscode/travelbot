<?php

namespace App\Command;

use App\Service\TravelRecommenderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-bedrock',
    description: 'Test AWS Bedrock integration with Claude',
)]
class TestBedrockCommand extends Command
{
    public function __construct(
        private TravelRecommenderService $travelRecommender
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fast', null, InputOption::VALUE_NONE, 'Use fast model (Haiku)')
            ->addOption('stream', null, InputOption::VALUE_NONE, 'Stream response')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $useFast = $input->getOption('fast');
        $useStream = $input->getOption('stream');

        $testMessage = "Hello! I'm looking for a romantic destination in Europe for my honeymoon. Budget is around $100 per day. Any recommendations?";
        
        $io->title('Testing AWS Bedrock Integration');
        $io->text('Test message: ' . $testMessage);
        $io->text('Using model: ' . ($useFast ? 'Haiku (fast)' : 'Sonnet (detailed)'));
        $io->newLine();

        try {
            if ($useStream) {
                $io->text('Streaming response...');
                $io->newLine();
                
                foreach ($this->travelRecommender->streamRecommendation($testMessage, null, [], $useFast) as $chunk) {
                    if ($chunk['type'] === 'content') {
                        $output->write($chunk['text']);
                    } elseif ($chunk['type'] === 'stop') {
                        $io->newLine();
                        $io->text('\n[Response complete - Model: ' . $chunk['model'] . ']');
                        break;
                    } elseif ($chunk['type'] === 'error') {
                        $io->error('Error: ' . $chunk['message']);
                        return Command::FAILURE;
                    }
                }
            } else {
                $response = $this->travelRecommender->generateRecommendation($testMessage, null, [], $useFast);
                
                $io->section('Response:');
                $io->text($response['content']);
                $io->newLine();
                $io->text('Model used: ' . $response['model']);
                
                if ($response['usage']) {
                    $io->text('Tokens - Input: ' . ($response['usage']['inputTokens'] ?? 'N/A') . 
                             ', Output: ' . ($response['usage']['outputTokens'] ?? 'N/A'));
                }
            }

            $io->success('Bedrock integration test completed successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Bedrock test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
