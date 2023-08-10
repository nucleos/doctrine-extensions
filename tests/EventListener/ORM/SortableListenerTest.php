<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\EventListener\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nucleos\Doctrine\EventListener\ORM\SortableListener;
use Nucleos\Doctrine\Tests\Fixtures\ClassWithAllProperties;
use Nucleos\Doctrine\Tests\Fixtures\EmptyClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class SortableListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $listener = new SortableListener();

        static::assertSame([
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::loadClassMetadata,
        ], $listener->getSubscribedEvents());
    }

    public function testPrePersistForInvalidClass(): void
    {
        $object = $this->createMock(stdClass::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('createQueryBuilder');

        $listener = new SortableListener();
        $listener->prePersist(new PrePersistEventArgs($object, $entityManager));
    }

    public function testPreRemoveForInvalidClass(): void
    {
        $object = $this->createMock(stdClass::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('createQueryBuilder');

        $listener = new SortableListener();
        $listener->preRemove(new PreRemoveEventArgs($object, $entityManager));
    }

    public function testLoadClassMetadataWithEmptyClass(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn(null)
        ;
        $metadata->expects(static::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new SortableListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithInvalidClass(): void
    {
        $reflection = new ReflectionClass(EmptyClass::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->expects(static::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new SortableListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithValidClass(): void
    {
        $reflection = new ReflectionClass(ClassWithAllProperties::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->method('hasField')->with('position')
            ->willReturn(false)
        ;
        $metadata->expects(static::once())->method('mapField')->with([
            'type'      => 'integer',
            'fieldName' => 'position',
        ]);

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new SortableListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithExistingProperty(): void
    {
        $reflection = new ReflectionClass(ClassWithAllProperties::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->method('hasField')->with('position')
            ->willReturn(true)
        ;
        $metadata->expects(static::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new SortableListener();
        $listener->loadClassMetadata($eventArgs);
    }
}
