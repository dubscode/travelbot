<?php

namespace App\Doctrine\Middleware;

use App\Doctrine\Driver\PostgreSQLPlatformDriver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

#[AsMiddleware]
final class PostgreSQLPlatformMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new PostgreSQLPlatformDriver($driver);
    }
}