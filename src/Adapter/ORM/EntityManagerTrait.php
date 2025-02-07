<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Adapter\ORM;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @deprecated since nucleos/doctrine-extensions 4.2
 */
trait EntityManagerTrait
{
    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     */
    final protected function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return $this->getRepository()
            ->createQueryBuilder($alias, $indexBy)
        ;
    }

    /**
     * Returns the related Object Repository.
     *
     * @return EntityRepository
     */
    abstract protected function getRepository();
}
