<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-type ForeignKey = array{
 *     table:       string,
 *     key:         string,
 *     uuid_key:    string,
 *     nullable:    bool,
 *     name:        string,
 *     primaryKey:  string[],
 *     onDelete?:   string
 * }
 * @phpstan-type Index = array{
 *     table:   string,
 *     name:    string,
 *     columns: string[],
 *     primary: bool,
 *     unique:  bool
 * }
 */
final class IdToUuidMigration implements LoggerAwareInterface
{
    use LogAwareMigration;

    public const UUID_FIELD  = 'uuid';
    public const UUID_TYPE   = Types::STRING;
    public const UUID_LENGTH = 36;

    /**
     * @var array<int|string, string>
     */
    private array $idToUuidMap = [];

    /**
     * @var array<array-key, array<string, mixed>>
     *
     * @phpstan-var array<array-key, ForeignKey>
     */
    private array $foreignKeys = [];

    /**
     * @var array<array-key, array<string, mixed>>
     *
     * @phpstan-var array<array-key, Index>
     */
    private array $indexes = [];

    private string $idField;

    private string $table;

    private Connection $connection;

    private AbstractSchemaManager $schemaManager;

    public function __construct(Connection $connection, ?LoggerInterface $logger = null)
    {
        $this->connection    = $connection;
        $this->schemaManager = $this->connection->createSchemaManager();
        $this->logger        = $logger ?? new NullLogger();
    }

    /**
     * @param callable(mixed $id, string $uuid): void|null $callback
     */
    public function migrate(string $tableName, string $idField = 'id', ?callable $callback = null): void
    {
        $this->section(\sprintf('Migrating %s.%s field to UUID', $tableName, $idField));

        $this->table       = $tableName;
        $this->idField     = $idField;

        $this->findForeignKeys();
        $this->findIndexes();
        $this->addUuidFields();
        $this->generateUuidsToReplaceIds();
        $this->addThoseUuidsToTablesWithFK();
        $this->handleCallback($callback);
        $this->deletePreviousFKs();
        $this->deleteIndexes();
        $this->deletePrimaryKeys();
        $this->recreateIdFields();
        $this->syncIdFields();
        $this->dropTemporyForeignKeyUuidFields();
        $this->dropIdPrimaryKeyAndSetUuidToPrimaryKey();
        $this->restoreConstraintsAndIndexes();
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function findForeignKeys(): void
    {
        $this->foreignKeys = [];

        foreach ($this->schemaManager->listTables() as $table) {
            $foreignKeys = $this->schemaManager->listTableForeignKeys($table->getName());

            foreach ($foreignKeys as $foreignKey) {
                $key = $foreignKey->getLocalColumns()[0];

                if ($foreignKey->getForeignTableName() !== $this->table) {
                    continue;
                }

                $meta = [
                    'table'        => $table->getName(),
                    'key'          => $key,
                    'uuid_key'     => $key.'_to_uuid',
                    'nullable'     => $this->isForeignKeyNullable($table, $key),
                    'name'         => $foreignKey->getName(),
                    'primaryKey'   => $this->getPrimaryKeyColumns($table),
                ];

                $onDelete = $foreignKey->onDelete();
                if (null !== $onDelete) {
                    $meta['onDelete'] = $onDelete;
                }

                $this->foreignKeys[] = $meta;
            }
        }

        if (0 === \count($this->foreignKeys)) {
            $this->info('No foreign keys detected');

            return;
        }

        $this->section('Detected foreign keys:');

        foreach ($this->foreignKeys as $meta) {
            $this->section('  * '.$meta['table'].'.'.$meta['key']);
        }
    }

    private function findIndexes(): void
    {
        $this->indexes = [];

        foreach ($this->schemaManager->listTables() as $table) {
            foreach ($table->getIndexes() as $index) {
                if (!$this->hasForeignKeyColumns($table->getName(), $index->getColumns())) {
                    continue;
                }

                $this->indexes[] = [
                    'table'   => $table->getName(),
                    'name'    => $index->getName(),
                    'columns' => $index->getColumns(),
                    'primary' => $index->isPrimary(),
                    'unique'  => $index->isUnique(),
                ];
            }
        }

        if (0 === \count($this->foreignKeys)) {
            $this->info('No indexes detected');

            return;
        }

        $this->section('Detected indexes:');

        foreach ($this->indexes as $meta) {
            $this->section('  * '.$meta['table'].'.'.implode('|', $meta['columns']));
        }
    }

    /**
     * @param string[] $columns
     */
    private function hasForeignKeyColumns(string $table, array $columns): bool
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey['table'] !== $table) {
                continue;
            }

            if (\in_array($foreignKey['key'], $columns, true)) {
                return true;
            }
        }

        return false;
    }

    private function addUuidFields(): void
    {
        $this->section('Adding new "uuid" fields');

        $schema = $this->schemaManager->introspectSchema();

        $table = $schema->getTable($this->table);
        $table->addColumn(self::UUID_FIELD, self::UUID_TYPE, [
            'length'              => self::UUID_LENGTH,
            'notnull'             => false,
        ]);

        foreach ($this->foreignKeys as $foreignKey) {
            $table = $schema->getTable($foreignKey['table']);
            $table->addColumn($foreignKey['uuid_key'], self::UUID_TYPE, [
                'length'              => self::UUID_LENGTH,
                'notnull'             => false,
                'platformOptions'     => ['FIRST'],
            ]);
        }

        $this->updateSchema($schema);
    }

    private function generateUuidsToReplaceIds(): void
    {
        $this->idToUuidMap = [];

        $fetchs = $this->connection->fetchAllAssociative(\sprintf('SELECT %s from %s', $this->idField, $this->table));

        if (0 === \count($fetchs)) {
            return;
        }

        $this->section('Generating '.\count($fetchs).' UUIDs');

        foreach ($fetchs as $fetch) {
            $id                     = $fetch[$this->idField];
            $uuid                   = Uuid::v4()->toRfc4122();
            $this->idToUuidMap[$id] = $uuid;
            $this->connection->update($this->table, [
                self::UUID_FIELD => $uuid,
            ], [
                $this->idField => $id,
            ]);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function addThoseUuidsToTablesWithFK(): void
    {
        if (0 === \count($this->foreignKeys)) {
            return;
        }

        $this->section('Adding UUIDs to tables with foreign keys');

        foreach ($this->foreignKeys as $foreignKey) {
            $primaryKeys = array_map(static fn (string $column) => $column, $foreignKey['primaryKey']);

            $selectPk = implode(',', $primaryKeys);

            $fetchs   = $this->connection->fetchAllAssociative('SELECT '.$selectPk.', '.$foreignKey['key'].' FROM '.$foreignKey['table']);

            if (0 === \count($fetchs)) {
                continue;
            }

            $this->debug('Adding '.\count($fetchs).' UUIDs to "'.$foreignKey['table'].'.'.$foreignKey['key']);

            foreach ($fetchs as $fetch) {
                if (null === $fetch[$foreignKey['key']]) {
                    continue;
                }

                $queryPk = array_flip($primaryKeys);
                foreach ($queryPk as $key => $value) {
                    $queryPk[$key] = $fetch[$key];
                }

                $this->connection->update($foreignKey['table'], [
                    $foreignKey['uuid_key'] => $this->idToUuidMap[$fetch[$foreignKey['key']]],
                ], $queryPk);
            }
        }
    }

    /**
     * @param callable(mixed $id, string $uuid): void|null $callback
     */
    private function handleCallback(?callable $callback): void
    {
        if (null === $callback) {
            return;
        }

        $this->section('Executing callback');

        foreach ($this->idToUuidMap as $old => $new) {
            $callback($old, $new);
        }
    }

    private function deleteIndexes(): void
    {
        $this->section('Deleting indexes');

        $schema = $this->schemaManager->introspectSchema();

        foreach ($this->indexes as $index) {
            $table = $schema->getTable($index['table']);

            if ($table->hasIndex($index['name']) && !$index['primary']) {
                $table->dropIndex($index['name']);
            }
        }

        $this->updateSchema($schema);
    }

    private function deletePreviousFKs(): void
    {
        $this->section('Deleting previous foreign keys');

        $schema = $this->schemaManager->introspectSchema();

        foreach ($this->foreignKeys as $foreignKey) {
            $table = $schema->getTable($foreignKey['table']);

            if ($table->hasForeignKey($foreignKey['name'])) {
                $table->removeForeignKey($foreignKey['name']);
            }
        }

        $this->updateSchema($schema);
    }

    private function deletePrimaryKeys(): void
    {
        $this->section('Deleting previous primary keys');

        $schema = $this->schemaManager->introspectSchema();

        foreach ($this->foreignKeys as $foreignKey) {
            $table = $schema->getTable($foreignKey['table']);

            if ($this->hasCombinedPrimaryKey($foreignKey)) {
                $table->dropPrimaryKey();
            }
        }

        $this->updateSchema($schema);
    }

    private function recreateIdFields(): void
    {
        $this->section('Recreate id fields');

        $schema = $this->schemaManager->introspectSchema();

        $table  = $schema->getTable($this->table);
        $table->dropColumn($this->idField);
        $table->addColumn($this->idField, self::UUID_TYPE, [
            'length'              => self::UUID_LENGTH,
            'notnull'             => false,
            'platformOptions'     => ['FIRST'],
        ]);

        foreach ($this->foreignKeys as $foreignKey) {
            $table  = $schema->getTable($foreignKey['table']);

            $table->dropColumn($foreignKey['key']);
            $table->addColumn($foreignKey['key'], self::UUID_TYPE, [
                'length'  => self::UUID_LENGTH,
                'notnull' => false,
            ]);
        }

        $this->updateSchema($schema);
    }

    private function syncIdFields(): void
    {
        $this->section('Copy UUIDs to recreated ids fields');

        $this->connection->executeQuery(\sprintf('UPDATE %s SET %s = %s', $this->table, $this->idField, self::UUID_FIELD));

        foreach ($this->foreignKeys as $foreignKey) {
            $this->connection->executeQuery(\sprintf('UPDATE %s SET %s = %s', $foreignKey['table'], $foreignKey['key'], $foreignKey['uuid_key']));
        }
    }

    private function dropTemporyForeignKeyUuidFields(): void
    {
        $this->section('Drop temporary foreign key uuid fields');

        $schema = $this->schemaManager->introspectSchema();

        foreach ($this->foreignKeys as $foreignKey) {
            $table  = $schema->getTable($foreignKey['table']);

            $table->dropColumn($foreignKey['uuid_key']);
        }

        $this->updateSchema($schema);
    }

    private function dropIdPrimaryKeyAndSetUuidToPrimaryKey(): void
    {
        $this->section('Creating the new primary key');

        $schema = $this->schemaManager->introspectSchema();
        $table  = $schema->getTable($this->table);

        $table->dropPrimaryKey();
        $table->dropColumn(self::UUID_FIELD);

        $table->setPrimaryKey([$this->idField]);

        $this->updateSchema($schema);
    }

    private function restoreConstraintsAndIndexes(): void
    {
        $this->section('Restore constraints and indexes');

        $schema = $this->schemaManager->introspectSchema();

        foreach ($this->foreignKeys as $foreignKey) {
            $table = $schema->getTable($foreignKey['table']);

            if ([] !== $foreignKey['primaryKey']) {
                $primaryKeys = array_map(static fn (string $column) => $column, $foreignKey['primaryKey']);

                if (null === $table->getPrimaryKey()) {
                    $table->setPrimaryKey($primaryKeys);
                }
            }

            $table->modifyColumn($foreignKey['key'], [
                'notnull' => !$foreignKey['nullable'],
            ]);
            $table->addForeignKeyConstraint($this->table, [$foreignKey['key']], [$this->idField], [
                'onDelete' => $foreignKey['onDelete'] ?? null,
            ]);
            $table->addIndex([$foreignKey['key']]);
        }

        foreach ($this->indexes as $index) {
            $table = $schema->getTable($index['table']);

            if ($index['unique'] && !$index['primary']) {
                $table->addUniqueIndex($index['columns'], $index['name']);
            }
        }

        $this->updateSchema($schema);
    }

    /**
     * @phpstan-param ForeignKey $foreignKey
     */
    private function hasCombinedPrimaryKey(array $foreignKey): bool
    {
        /** @var Column $key */
        foreach ($foreignKey['primaryKey'] as $key) {
            if ($key === $foreignKey['key']) {
                return true;
            }
        }

        return false;
    }

    private function isForeignKeyNullable(Table $table, string $key): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getName() === $key) {
                return !$column->getNotnull();
            }
        }

        throw new RuntimeException('Unable to find '.$key.' in '.$table->getName());
    }

    private function updateSchema(Schema $schema): void
    {
        $this->schemaManager->migrateSchema($schema);
    }

    /**
     * @return string[]
     */
    private function getPrimaryKeyColumns(Table $table): array
    {
        $primaryKey = $table->getPrimaryKey();

        if (null === $primaryKey) {
            throw new Exception('Table '.$table->getName().' has no primary key.');
        }

        return $primaryKey->getColumns();
    }
}
