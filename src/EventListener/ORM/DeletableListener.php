<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\EventListener\ORM;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Nucleos\Doctrine\Model\DeletableInterface;

final class DeletableListener extends AbstractListener
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
        ];
    }

    /**
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $meta = $eventArgs->getClassMetadata();

        $reflClass = $meta->getReflectionClass();

        if (null === $reflClass || !$reflClass->implementsInterface(DeletableInterface::class)) {
            return;
        }

        $this->createDateTimeField($meta, 'deletedAt', true);
    }
}
