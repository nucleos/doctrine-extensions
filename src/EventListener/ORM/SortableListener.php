<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\EventListener\ORM;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Nucleos\Doctrine\Model\PositionAwareInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class SortableListener implements EventSubscriber
{
    private PropertyAccessor $propertyAccessor;

    public function __construct(?PropertyAccessor $propertyAccessor = null)
    {
        if (null === $propertyAccessor) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        $this->propertyAccessor = $propertyAccessor;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::loadClassMetadata,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!$args->getObject() instanceof PositionAwareInterface) {
            return;
        }

        $this->uniquePosition($args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$args->getObject() instanceof PositionAwareInterface) {
            return;
        }

        $position = $args->getObject()->getPosition();

        if ($args->hasChangedField('position')) {
            $position = $args->getOldValue('position');
        }

        $this->uniquePosition($args, $position);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof PositionAwareInterface) {
            $this->movePosition($args->getObjectManager(), $entity, -1);
        }
    }

    /**
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $meta = $eventArgs->getClassMetadata();

        $reflClass = $meta->getReflectionClass();

        if (null === $reflClass || !$reflClass->implementsInterface(PositionAwareInterface::class)) {
            return;
        }

        if (!$meta->hasField('position')) {
            $meta->mapField(
                [
                    'type'      => 'integer',
                    'fieldName' => 'position',
                ]
            );
        }
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    private function uniquePosition(LifecycleEventArgs $args, ?int $oldPosition = null): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof PositionAwareInterface) {
            return;
        }

        $em = $args->getObjectManager();

        if (null === $entity->getPosition()) {
            $position = $this->getNextPosition($em, $entity);
            $entity->setPosition($position);
        } elseif (null !== $oldPosition && $oldPosition !== $entity->getPosition()) {
            $this->movePosition($em, $entity);
        }
    }

    private function movePosition(EntityManagerInterface $em, PositionAwareInterface $entity, int $direction = 1): void
    {
        $uow  = $em->getUnitOfWork();
        $meta = $em->getClassMetadata(\get_class($entity));

        $qb = $em->createQueryBuilder()
            ->update($meta->getName(), 'e')
            ->set('e.position', 'e.position + '.$direction)
        ;

        if ($direction > 0) {
            $qb->andWhere('e.position <= :position')->setParameter('position', $entity->getPosition());
        } elseif ($direction < 0) {
            $qb->andWhere('e.position >= :position')->setParameter('position', $entity->getPosition());
        } else {
            return;
        }

        $this->addGroupFilter($qb, $entity, $uow);

        $qb->getQuery()->execute();
    }

    private function getNextPosition(EntityManagerInterface $em, PositionAwareInterface $entity): int
    {
        $meta = $em->getClassMetadata(\get_class($entity));

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from($meta->getName(), 'e')
            ->addOrderBy('e.position', 'DESC')
            ->setMaxResults(1)
        ;

        $this->addGroupFilter($qb, $entity);

        try {
            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result instanceof PositionAwareInterface && null !== $result->getPosition()) {
                return $result->getPosition() + 1;
            }
        } catch (NonUniqueResultException $ignored) {
        }

        return 0;
    }

    private function addGroupFilter(QueryBuilder $qb, PositionAwareInterface $entity, ?UnitOfWork $uow = null): void
    {
        foreach ($entity->getPositionGroup() as $field) {
            $value = $this->propertyAccessor->getValue($entity, $field);

            if (\is_object($value) && (null === $uow || null === $uow->getSingleIdentifierValue($value))) {
                continue;
            }

            $qb->andWhere('e.'.$field.' = :'.$field)->setParameter($field, $value);
        }
    }
}
