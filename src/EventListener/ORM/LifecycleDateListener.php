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
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
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

    public function prePersist(PrePersistEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof LifecycleDateTimeInterface) {
            $object->setCreatedAt(new DateTime());
            $object->setUpdatedAt(new DateTime());
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof LifecycleDateTimeInterface) {
            $object->setUpdatedAt(new DateTime());
        }
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $meta = $eventArgs->getClassMetadata();

        $reflClass = $meta->getReflectionClass();

        if (!$reflClass->implementsInterface(LifecycleDateTimeInterface::class)) {
            return;
        }

        $this->createDateTimeField($meta, 'createdAt', false);
        $this->createDateTimeField($meta, 'updatedAt', false);
    }
}
