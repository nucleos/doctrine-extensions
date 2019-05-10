<?php

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Core23\Doctrine\Tests\Manager\ORM;

use Core23\Doctrine\Tests\Fixtures\DemoEntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class BaseQueryTraitTest extends TestCase
{
    private $manager;

    protected function setUp()
    {
        $repository = $this->prophesize(EntityRepository::class);

        $this->manager = new DemoEntityManager($repository->reveal());
    }

    public function testAddOrder(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('myalias.position', 'asc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            ['position'],
            'myalias'
        ));
    }

    public function testAddOrderWithOrder(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('myalias.position', 'desc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            ['position'],
            'myalias',
            [],
            'desc'
        ));
    }

    public function testAddOrderWithMultpleSorts(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('myalias.position', 'desc')
            ->shouldBeCalled()
        ;
        $builder->addOrderBy('myalias.otherfield', 'desc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            ['position', 'otherfield'],
            'myalias',
            [],
            'desc'
        ));
    }

    public function testAddOrderWithMultpleSortAndOrders(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('myalias.position', 'desc')
            ->shouldBeCalled()
        ;
        $builder->addOrderBy('myalias.otherfield', 'asc')
            ->shouldBeCalled()
        ;
        $builder->addOrderBy('myalias.foo', 'desc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            [
                'position',
                'otherfield' => 'asc',
                'foo'        => 'desc',
            ],
            'myalias',
            [],
            'desc'
        ));
    }

    public function testAddOrderWithChildOrder(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('child.position', 'desc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            ['child.position'],
            'myalias',
            [],
            'desc'
        ));
    }

    public function testAddOrderWithAliasChildOrder(): void
    {
        $builder = $this->prophesize(QueryBuilder::class);

        $builder->addOrderBy('foo.position', 'desc')
            ->shouldBeCalled()
        ;

        static::assertSame($builder->reveal(), $this->manager->addOrderToQueryBuilder(
            $builder->reveal(),
            ['f.position'],
            'myalias',
            ['f' => 'foo'],
            'desc'
        ));
    }
}
