<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a test user for development',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        
        // Set some travel preferences
        $user->setBudget('100.00');
        $user->setInterests(['culture', 'food', 'history', 'romantic']);
        $user->setClimatePreferences(['temperate', 'mediterranean']);
        
        // Mark as verified (skip email verification for testing)
        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Test user created successfully!');
        $io->table(['Field', 'Value'], [
            ['Name', $user->getName()],
            ['Email', $user->getEmail()],
            ['Password', 'password123'],
            ['Budget', '$' . $user->getBudget() . '/day'],
            ['Interests', implode(', ', $user->getInterests())],
            ['Climate Prefs', implode(', ', $user->getClimatePreferences())],
        ]);

        return Command::SUCCESS;
    }
}
