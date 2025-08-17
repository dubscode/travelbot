<?php

namespace App\Doctrine\SchemaManager;

use Doctrine\DBAL\Schema\PostgreSQLSchemaManager as BasePostgreSQLSchemaManager;

final class PostgreSQLSchemaManager extends BasePostgreSQLSchemaManager
{
    /**
     * List of pgvector HNSW indexes to ignore during schema operations
     * This prevents Doctrine from trying to drop our custom vector indexes
     */
    private const IGNORED_INDEXES = [
        'idx_amenity_embedding_hnsw' => true,
        'idx_resort_category_embedding_hnsw' => true,
        'idx_destination_embedding_hnsw' => true,
    ];

    /**
     * Filter out ignored indexes from the portable table indexes list
     * This makes Doctrine treat our pgvector HNSW indexes as if they don't exist
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $indexes = parent::_getPortableTableIndexesList($tableIndexes, $tableName);

        foreach (array_keys($indexes) as $indexName) {
            if (isset(self::IGNORED_INDEXES[$indexName])) {
                unset($indexes[$indexName]);
            }
        }

        return $indexes;
    }
}