<?php

namespace App\Doctrine\Driver;

use App\Doctrine\Platform\PostgreSQLPlatform;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class PostgreSQLPlatformDriver extends AbstractDriverMiddleware
{
    public function getDatabasePlatform()
    {
        return new PostgreSQLPlatform();
    }

    public function createDatabasePlatformForVersion($version)
    {
        return new PostgreSQLPlatform();
    }
}