<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\Fixtures;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nucleos\Doctrine\Adapter\ORM\EntityManagerTrait;
use Nucleos\Doctrine\Manager\ORM\BaseQueryTrait;
use Nucleos\Doctrine\Manager\ORM\SearchQueryTrait;
use RuntimeException;

final class DemoEntityManager
{
    use BaseQueryTrait;
    use EntityManagerTrait;
    use SearchQueryTrait;

    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var class-string
     */
    private $class;

    /**
     * @param class-string $class
     */
    public function __construct(string $class, ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $this->class    = $class;
    }

    public function getQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return $this->createQueryBuilder($alias, $indexBy);
    }

    /**
     * @param mixed[] $values
     */
    public function searchWhereQueryBuilder(
        QueryBuilder $qb,
        string $field,
        array $values,
        bool $strict = false
    ): Composite {
        return $this->searchWhere($qb, $field, $values, $strict);
    }

    /**
     * @param string[]              $sort
     * @param array<string, string> $aliasMapping
     */
    public function addOrderToQueryBuilder(
        QueryBuilder $builder,
        array $sort,
        string $defaultEntity,
        array $aliasMapping = [],
        string $defaultOrder = 'asc'
    ): QueryBuilder {
        return $this->addOrder($builder, $sort, $defaultEntity, $aliasMapping, $defaultOrder);
    }

    /**
     * @return EntityRepository<object>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->getObjectManager()->getRepository($this->class);
    }

    private function getObjectManager(): ObjectManager
    {
        $manager = $this->registry->getManagerForClass($this->class);

        if (null === $manager) {
            throw new RuntimeException(
                \sprintf(
                    'Unable to find the mapping information for the class %s.'
                    .' Please check the `auto_mapping` option'
                    .' (http://symfony.com/doc/current/reference/configuration/doctrine.html#configuration-overview)'
                    .' or add the bundle to the `mappings` section in the doctrine configuration.',
                    $this->class
                )
            );
        }

        return $manager;
    }
}
