<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\EventListener\ORM;

use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Nucleos\Doctrine\Model\LifecycleDateTimeInterface;

final class LifecycleDateListener extends AbstractListener
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::loadClassMetadata,
        ];
    }

    /**
     * Start lifecycle.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof LifecycleDateTimeInterface) {
            $object->setCreatedAt(new DateTime());
            $object->setUpdatedAt(new DateTime());
        }
    }

    /**
     * Update LifecycleDateTime.
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof LifecycleDateTimeInterface) {
            $object->setUpdatedAt(new DateTime());
        }
    }

    /**
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $meta = $eventArgs->getClassMetadata();

        $reflClass = $meta->getReflectionClass();

        if (null === $reflClass || !$reflClass->implementsInterface(LifecycleDateTimeInterface::class)) {
            return;
        }

        $this->createDateTimeField($meta, 'createdAt', false);
        $this->createDateTimeField($meta, 'updatedAt', false);
    }
}
