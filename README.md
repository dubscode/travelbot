# TravelBot

AI-powered travel planning assistant using AWS Bedrock and Symfony.

## Database Commands

- **Generate migrations after entity changes:** `php bin/console make:migration`
- **Check migration status:** `php bin/console doctrine:migrations:status`
- **Run pending migrations:** `php bin/console doctrine:migrations:migrate`
- **Seed destinations data:** `php bin/console app:seed-destinations --clear`