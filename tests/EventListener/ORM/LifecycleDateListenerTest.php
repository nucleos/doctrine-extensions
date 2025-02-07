<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nucleos\Doctrine\Tests\EventListener\ORM;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nucleos\Doctrine\EventListener\ORM\LifecycleDateListener;
use Nucleos\Doctrine\Model\LifecycleDateTimeInterface;
use Nucleos\Doctrine\Tests\Fixtures\ClassWithAllProperties;
use Nucleos\Doctrine\Tests\Fixtures\EmptyClass;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class LifecycleDateListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $listener = new LifecycleDateListener();

        self::assertSame([
            Events::prePersist,
            Events::preUpdate,
            Events::loadClassMetadata,
        ], $listener->getSubscribedEvents());
    }

    public function testPrePersist(): void
    {
        $object = $this->createMock(LifecycleDateTimeInterface::class);
        $object->expects(self::once())->method('setCreatedAt');
        $object->expects(self::once())->method('setUpdatedAt');

        $eventArgs = new PrePersistEventArgs($object, self::createStub(EntityManagerInterface::class));

        $listener = new LifecycleDateListener();
        $listener->prePersist($eventArgs);
    }

    public function testPrePersistForInvalidClass(): void
    {
        $object = $this->createMock(stdClass::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('createQueryBuilder');

        $listener = new LifecycleDateListener();
        $listener->prePersist(new PrePersistEventArgs($object, $entityManager));
    }

    public function testPreUpdate(): void
    {
        $object = $this->createMock(LifecycleDateTimeInterface::class);
        $object->expects(self::once())->method('setUpdatedAt');

        $changeSet = [];

        $listener = new LifecycleDateListener();
        $listener->preUpdate(new PreUpdateEventArgs($object, self::createStub(EntityManagerInterface::class), $changeSet));
    }

    public function testPreUpdateForInvalidClass(): void
    {
        $object = $this->createMock(stdClass::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('createQueryBuilder');

        $changeSet = [];

        $listener = new LifecycleDateListener();
        $listener->preUpdate(new PreUpdateEventArgs($object, $entityManager, $changeSet));
    }

    public function testLoadClassMetadataWithEmptyClass(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn(null)
        ;
        $metadata->expects(self::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new LifecycleDateListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithInvalidClass(): void
    {
        $reflection = new ReflectionClass(EmptyClass::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->expects(self::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new LifecycleDateListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithValidClass(): void
    {
        $reflection = new ReflectionClass(ClassWithAllProperties::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->expects($matcher = self::exactly(2))->method('hasField')
            ->willReturnCallback($this->withParameter($matcher, [
                ['createdAt'],
                ['updatedAt'],
            ]))
            ->willReturn(false)
        ;
        $metadata->expects($matcher = self::exactly(2))->method('mapField')
            ->willReturnCallback($this->withParameter($matcher, [
                [[
                    'type'      => 'datetime',
                    'fieldName' => 'createdAt',
                    'nullable'  => false,
                ]],
                [[
                    'type'      => 'datetime',
                    'fieldName' => 'updatedAt',
                    'nullable'  => false,
                ]],
            ]))
        ;

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new LifecycleDateListener();
        $listener->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithExistingProperty(): void
    {
        $reflection = new ReflectionClass(ClassWithAllProperties::class);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')
            ->willReturn($reflection)
        ;
        $metadata->expects($matcher = self::exactly(2))->method('hasField')
            ->willReturnCallback($this->withParameter($matcher, [
                ['createdAt'],
                ['updatedAt'],
            ]))
            ->willReturn(true)
        ;
        $metadata->expects(self::never())->method('mapField');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $listener = new LifecycleDateListener();
        $listener->loadClassMetadata($eventArgs);
    }

    /**
     * @param array<array-key, mixed[]> $parameters
     */
    protected function withParameter(InvokedCount $matcher, array $parameters): Closure
    {
        return static function () use ($matcher, $parameters): void {
            $callNumber = $matcher->numberOfInvocations();

            Assert::assertEquals($parameters[$callNumber-1], \func_get_args(), \sprintf('Call %s', $callNumber));
        };
    }
}
