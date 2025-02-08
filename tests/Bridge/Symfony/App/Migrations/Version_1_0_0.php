<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\Bridge\Symfony\App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version_1_0_0 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial structure';
    }

    public function up(Schema $schema): void
    {
        $this->createMeal($schema);
        $this->createMealCategory($schema);
        $this->createMealAttribute($schema);
        $this->createIngredient($schema);
        $this->createCategory($schema);
        $this->createMenu($schema);
        $this->createMealPage($schema);
        $this->createPageCategory($schema);
        $this->createAttribute($schema);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    private function createMeal(Schema $schema): void
    {
        $table = $schema->createTable('acme__meal');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->setPrimaryKey(['id']);
    }

    private function createMealCategory(Schema $schema): void
    {
        $table = $schema->createTable('acme__meal_category');
        $table->addColumn('meal_id', Types::INTEGER);
        $table->addColumn('category_id', Types::INTEGER);
        $table->addIndex(['meal_id']);
        $table->addIndex(['category_id']);
        $table->addForeignKeyConstraint('acme__meal', ['meal_id'], ['id']);
        $table->addForeignKeyConstraint('acme__category', ['category_id'], ['id']);
        $table->setPrimaryKey(['meal_id', 'category_id']);
    }

    private function createMealAttribute(Schema $schema): void
    {
        $table = $schema->createTable('acme__meal_attribute');
        $table->addColumn('attribute_id', Types::INTEGER);
        $table->addColumn('meal_id', Types::INTEGER);
        $table->addIndex(['attribute_id']);
        $table->addIndex(['meal_id']);
        $table->addForeignKeyConstraint('acme__attribute', ['attribute_id'], ['id']);
        $table->addForeignKeyConstraint('acme__meal', ['meal_id'], ['id']);
        $table->setPrimaryKey(['attribute_id', 'meal_id']);
    }

    private function createIngredient(Schema $schema): void
    {
        $table = $schema->createTable('acme__ingredient');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('meal_id', Types::INTEGER, [
            'notnull' => false,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->addIndex(['meal_id']);
        $table->addForeignKeyConstraint('acme__meal', ['meal_id'], ['id']);
        $table->setPrimaryKey(['id']);
    }

    private function createCategory(Schema $schema): void
    {
        $table = $schema->createTable('acme__category');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function createMenu(Schema $schema): void
    {
        $table = $schema->createTable('acme__menu');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->setPrimaryKey(['id']);
    }

    private function createMealPage(Schema $schema): void
    {
        $table = $schema->createTable('acme__page');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('menu_id', Types::INTEGER, [
            'notnull' => false,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->addIndex(['menu_id']);
        $table->addForeignKeyConstraint('acme__menu', ['menu_id'], ['id']);
        $table->setPrimaryKey(['id']);
    }

    private function createPageCategory(Schema $schema): void
    {
        $table = $schema->createTable('acme__page_category');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('page_id', Types::INTEGER);
        $table->addColumn('category_id', Types::INTEGER, [
            'notnull' => false,
        ]);
        $table->addColumn('description', Types::TEXT, [
            'notnull' => false,
        ]);
        $table->addIndex(['page_id']);
        $table->addIndex(['category_id']);
        $table->addForeignKeyConstraint('acme__page', ['page_id'], ['id']);
        $table->addForeignKeyConstraint('acme__category', ['category_id'], ['id']);
        $table->setPrimaryKey(['id']);
    }

    private function createAttribute(Schema $schema): void
    {
        $table = $schema->createTable('acme__attribute');
        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
        ]);
        $table->addColumn('name', Types::STRING, [
            'length'  => 50,
        ]);
        $table->setPrimaryKey(['id']);
    }
}
