<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\ClassAliasFinder;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use PHPUnit\Framework\TestCase;

class ClassAliasFinderTest extends TestCase
{
    private ClassAliasFinder $object;
    
    protected function setUp(): void
    {
        $this->object = new ClassAliasFinder();
    }
    
    public function testFindAliasesForClass()
    {
        $reflectionClass = new \ReflectionClass(TestEntity::class);
        $aliases = $this->object->findAliasesForClass($reflectionClass, TestEntity::class);
        $this->assertCount(3, $aliases);
        $this->assertContains('TestEntity', $aliases);
        $this->assertContains('SomeWeirdRootAlias\Annotations\Tests\Entity\TestEntity', $aliases);
        $this->assertContains('YetAnotherWeirdOne\Tests\Entity\TestEntity', $aliases);
    }

    public function testFindClassForAlias()
    {
        $reflectionClass = new \ReflectionClass(TestEntity::class);
        $this->assertSame(TestEntity::class, $this->object->findClassForAlias($reflectionClass, 'TestEntity'));
        $this->assertSame(TestEntity::class, $this->object->findClassForAlias($reflectionClass, 'SomeWeirdRootAlias\Annotations\Tests\Entity\TestEntity'));
        $this->assertSame(TestEntity::class, $this->object->findClassForAlias($reflectionClass, 'YetAnotherWeirdOne\Tests\Entity\TestEntity'));
    }
}
