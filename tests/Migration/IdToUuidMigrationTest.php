<?php

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\AbstractManagerRegistry;
use Nucleos\Doctrine\Migration\IdToUuidMigration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IdToUuidMigrationTest extends KernelTestCase
{
    public function testMigrate(): void
    {
        $kernel = self::createKernel();
        $kernel->boot();

        $connection = $this->getConnection();

        $this->createSchema($connection);
        $this->insertData($connection);

        $migration = new IdToUuidMigration($connection);
        $migration->migrate('category');

        $this->verifyCategoryData($connection);
        $this->verifyItemData($connection);
    }

    private function createCategory(Schema $schema): void
    {
        $table = $schema->createTable('category');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('parent_id', Types::INTEGER, [
            'notnull' => false,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->addForeignKeyConstraint('category', ['parent_id'], ['id'], [
            'onDelete' => 'SET NULL',
        ]);
        $table->addUniqueIndex(['parent_id', 'name']);
        $table->addIndex(['name']);
        $table->addIndex(['parent_id']);
        $table->setPrimaryKey(['id']);
    }

    private function createItem(Schema $schema): void
    {
        $table = $schema->createTable('item');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('category_id', Types::INTEGER);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->addForeignKeyConstraint('category', ['category_id'], ['id'], [
            'onDelete' => 'CASCADE',
        ]);
        $table->addIndex(['category_id']);
        $table->setPrimaryKey(['id']);
    }

    private function getConnection(): Connection
    {
        $registry = self::getContainer()->get('doctrine');

        \assert($registry instanceof AbstractManagerRegistry);

        $connection = $registry->getConnection();

        \assert($connection instanceof Connection);

        return $connection;
    }

    private function createSchema(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        $schema        = $schemaManager->introspectSchema();
        $this->createCategory($schema);
        $this->createItem($schema);
        $schemaManager->migrateSchema($schema);

        self::assertTrue($schemaManager->tablesExist(['category']));
        self::assertTrue($schemaManager->tablesExist(['item']));
    }

    /**
     * @throws Exception
     */
    private function insertData(Connection $connection): void
    {
        $connection->insert('category', [
            'id'        => 1,
            'name'      => 'Main',
            'parent_id' => null,
        ]);
        $connection->insert('category', [
            'id'        => 2,
            'name'      => 'Sub 1',
            'parent_id' => 1,
        ]);
        $connection->insert('category', [
            'id'        => 3,
            'name'      => 'Sub 2',
            'parent_id' => 1,
        ]);
        $connection->insert('category', [
            'id'        => 4,
            'name'      => 'Sub Sub',
            'parent_id' => 3,
        ]);

        $connection->insert('item', [
            'id'          => 1,
            'name'        => 'Item 1',
            'category_id' => 1,
        ]);
        $connection->insert('item', [
            'id'          => 2,
            'name'        => 'Item 2',
            'category_id' => 2,
        ]);
        $connection->insert('item', [
            'id'          => 3,
            'name'        => 'Item 3',
            'category_id' => 3,
        ]);
    }

    private function verifyCategoryData(Connection $connection): void
    {
        $result = $connection->fetchAllAssociative('SELECT id, parent_id FROM category');
        self::assertCount(4, $result);

        foreach ($result as $data) {
            self::assertTrue(36 === \strlen($data['id']));
        }
    }

    private function verifyItemData(Connection $connection): void
    {
        $result = $connection->fetchAllAssociative('SELECT id, category_id FROM item');
        self::assertCount(3, $result);

        foreach ($result as $data) {
            self::assertFalse(36 === \strlen($data['id']));
        }
    }
}
