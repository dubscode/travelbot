<?php

namespace App\Doctrine\Platform;

use App\Doctrine\SchemaManager\PostgreSQLSchemaManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform as BasePostgreSQLPlatform;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager as BasePostgreSQLSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

final class PostgreSQLPlatform extends BasePostgreSQLPlatform implements SchemaManagerFactory
{
    public function createSchemaManager(Connection $connection): BasePostgreSQLSchemaManager
    {
        return new PostgreSQLSchemaManager($connection, $this);
    }
}