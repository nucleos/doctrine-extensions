<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Manager\ORM;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * @deprecated since nucleos/doctrine-extensions 4.2
 */
trait BaseQueryTrait
{
    /**
     * @param array<int|string, string> $sort
     * @param array<string, string>     $aliasMapping
     */
    final protected function addOrder(QueryBuilder $builder, array $sort, string $defaultAlias, array $aliasMapping = [], string $defaultOrder = 'asc'): QueryBuilder
    {
        foreach ($sort as $field => $order) {
            if (\is_int($field)) {
                $field = $order;
                $order = $defaultOrder;
            }

            $this->addOrderField($builder, $defaultAlias, $field, $order, $aliasMapping);
        }

        return $builder;
    }

    /**
     * @param array<string, string> $aliasMapping
     */
    private function addOrderField(
        QueryBuilder $builder,
        string $table,
        string $field,
        string $order,
        array $aliasMapping = []
    ): void {
        $fieldSpl = explode('.', $field);

        if (\count($fieldSpl) > 2) {
            throw new InvalidArgumentException(\sprintf('The fieldname "%s" cannot contain more than one dot', $field));
        }

        // Map entity to table name
        if (2 === \count($fieldSpl)) {
            [$table, $field] = $fieldSpl;

            foreach ($aliasMapping as $k => $v) {
                if ($fieldSpl[0] === $k) {
                    $table = $v;

                    break;
                }
            }
        }

        $builder->addOrderBy($table.'.'.$field, $order);
    }
}
