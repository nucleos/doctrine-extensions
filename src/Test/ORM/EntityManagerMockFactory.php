<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Test\ORM;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @deprecated since nucleos/doctrine-extensions 4.2
 */
final class EntityManagerMockFactory
{
    /**
     * @param string[] $fields
     *
     * @return EntityManager|MockObject
     */
    public static function create(TestCase $test, Closure $qbCallback, array $fields): MockObject
    {
        $qb = $test->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();

        self::prepareQueryBuilder($test, $qb);

        $qbCallback($qb);

        $repository = $test->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $repository->method('createQueryBuilder')->willReturn($qb);

        $metadata = self::prepareMetadata($test, $fields);

        $connection = $test->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $em = $test->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();
        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    private static function prepareQueryBuilder(TestCase $test, MockObject $qb): void
    {
        $query = $test->getMockBuilder(AbstractQuery::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $query->method('execute')->willReturn(true);

        $qb->method('select')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);
        $qb->method('where')->willReturn($qb);
        $qb->method('orderBy')->willReturn($qb);
        $qb->method('andWhere')->willReturn($qb);
        $qb->method('leftJoin')->willReturn($qb);
    }

    /**
     * @param string[] $fields
     */
    private static function prepareMetadata(TestCase $test, array $fields): MockObject
    {
        if (class_exists(EntityManager::class)) {
            return $test->createMock(ClassMetadata::class);
        }

        $metadata = $test->getMockBuilder(ClassMetadataInfo::class)->disableOriginalConstructor()->getMock();
        $metadata->method('getFieldNames')->willReturn($fields);
        $metadata->method('getName')->willReturn('className');
        $metadata->method('getIdentifier')->willReturn(['id']);
        $metadata->method('getTableName')->willReturn('dummy');

        return $metadata;
    }
}
