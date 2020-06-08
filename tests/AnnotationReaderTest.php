<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\AnnotationGeneric;
use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Annotations\AnnotationReaderException;
use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Annotations\CachedAnnotationReader;
use Objectiphy\Annotations\PsrSimpleCacheInterface;
use Objectiphy\Annotations\Tests\Annotations\Column;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use Objectiphy\Annotations\Tests\Annotations\Relationship;
use Objectiphy\Annotations\Tests\Annotations\Table;
use Objectiphy\Annotations\Tests\Entity\TestEntitySubClass;
use PHPUnit\Framework\TestCase;

class AnnotationReaderTest extends TestCase
{
    private AnnotationReaderInterface $object;

    protected function setUp(): void
    {
        $this->object = new AnnotationReader(null, null, ['childClassName', 'targetEntity']);
    }

    public function testGetAnnotationFromClass()
    {
        $table = $this->object->getAnnotationFromClass(TestEntity::class, Table::class);
        $this->assertInstanceOf(Table::class, $table);
        $this->assertSame('test', $table->name);

        $this->object->setThrowExceptions(false);
        $error = $this->object->getAnnotationFromClass('MadeupClass', Table::class);
        $this->assertNull($error);
        $this->assertStringContainsString('exist', $this->object->lastErrorMessage);

        $this->object->setThrowExceptions(true);
        $this->expectException(AnnotationReaderException::class);
        $error = $this->object->getAnnotationFromClass('MadeupClass', Table::class);
    }

    public function testGetAnnotationFromProperty()
    {
        $relationship = $this->object->getAnnotationFromProperty(TestEntity::class, 'cb', Relationship::class);
        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertSame('one_to_one', $relationship->relationshipType);
        $this->assertSame('some\ns\ClassB', $relationship->getChildClassName());

        //Unqualified
        $column = $this->object->getAnnotationFromProperty(TestEntity::class, 'unqualifiedAnnotation', Column::class);
        $this->assertInstanceOf(Column::class, $column);
        $this->assertSame('int', $column->type);
        $this->assertSame('some_column_or_other', $column->name);
        
        //Sub class, referring to a property on the super class
        $this->object->setClassNameAttributes([]);
        $relationship2 = $this->object->getAnnotationFromProperty(TestEntitySubClass::class, 'cb', Relationship::class);
        $this->assertSame('ClassB', $relationship2->getChildClassName());

        //Generic on the sub class
        $var = $this->object->getAnnotationFromProperty(TestEntitySubClass::class, 'meh', 'var');
        $this->assertSame(AnnotationGeneric::class, get_class($var));
        $this->assertSame('int', $var->type);

        //Property does not exist
        $this->object->setThrowExceptions(false);
        $error = $this->object->getAnnotationFromProperty(TestEntity::class, 'madupProperty', Relationship::class);
        $this->assertNull($error);
        $this->assertStringContainsString('property', $this->object->lastErrorMessage);
    }

    public function testGetAnnotationFromMethod()
    {
        $param = $this->object->getAnnotationFromMethod(TestEntity::class, 'someMethod', 'param');
        $this->assertInstanceOf(AnnotationGeneric::class, $param);
        $this->assertSame('string', $param->type);
        $this->assertSame('$someArg', $param->variable);

        //Method does not exist
        $this->object->setThrowExceptions(false);
        $error = $this->object->getAnnotationFromMethod(TestEntity::class, 'madeupMethod', 'param');
        $this->assertNull($error);
        $this->assertStringContainsString('method', $this->object->lastErrorMessage);
    }

    public function testGetClassAnnotation()
    {
        $table = $this->object->getClassAnnotation(new \ReflectionClass(TestEntity::class), Table::class);
        $this->assertInstanceOf(Table::class, $table);
        $this->assertSame('test', $table->name);
    }

    public function testGetPropertyAnnotation()
    {
        $relationship = $this->object->getPropertyAnnotation(new \ReflectionProperty(TestEntity::class, 'cb'), Relationship::class);
        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertSame('one_to_one', $relationship->relationshipType);
        $this->assertSame('some\ns\ClassB', $relationship->getChildClassName());

        $this->object->setClassNameAttributes([]);
        $relationship2 = $this->object->getPropertyAnnotation(new \ReflectionProperty(TestEntity::class, 'cb'), Relationship::class);
        $this->assertSame('ClassB', $relationship2->getChildClassName());
    }

    public function testGetMethodAnnotation()
    {
        $param = $this->object->getMethodAnnotation(new \ReflectionMethod(TestEntity::class, 'someMethod'), 'param');
        $this->assertInstanceOf(AnnotationGeneric::class, $param);
        $this->assertSame('string', $param->type);
        $this->assertSame('$someArg', $param->variable);
    }

    public function testGetClassAnnotations()
    {
        $annotations = $this->object->getClassAnnotations(new \ReflectionClass(TestEntity::class));
        //@package Objectiphy\Annotations\Tests\Entity
        //@Objectiphy\Objectiphy\Annotation\Table(name="test")
        $this->assertSame(2, count($annotations));
        $this->assertInstanceOf(AnnotationGeneric::class, $annotations[0]);
        $this->assertSame('package', $annotations[0]->name);
        $this->assertSame('Objectiphy\Annotations\Tests\Entity', $annotations[0]->value);
        $this->assertInstanceOf(Table::class, $annotations[1]);
        $this->assertSame('test', $annotations[1]->name);
    }

    public function testGetPropertyAnnotations()
    {
        $reflectionProperty = new \ReflectionProperty(TestEntity::class, 'cb');
        $annotations = $this->object->getPropertyAnnotations($reflectionProperty);
        //@var ClassB
        //@ObjectiphyRelationship(childClassName="ClassB", relationshipType="one_to_one")
        $this->assertSame(2, count($annotations));
        $this->assertInstanceOf(AnnotationGeneric::class, $annotations[0]);
        $this->assertSame('some\ns\ClassB', $annotations[0]->type);
        $this->assertInstanceOf(Relationship::class, $annotations[1]);
        $this->assertSame('one_to_one', $annotations[1]->relationshipType);
        $this->assertSame('some\ns\ClassB', $annotations[1]->getChildClassName());
    }

    public function testGetMethodAnnotations()
    {
        $reflectionMethod = new \ReflectionMethod(TestEntity::class, 'otherMethod');
        $annotations = $this->object->getMethodAnnotations($reflectionMethod);
        //@param ClassB $cb
        //@param ClassB $cb With a comment
        $this->assertSame(2, count($annotations));
        $this->assertInstanceOf(AnnotationGeneric::class, $annotations[0]);
        $this->assertInstanceOf(AnnotationGeneric::class, $annotations[1]);
        $this->assertSame('some\ns\ClassB', $annotations[0]->type);
        $this->assertSame('$cb', $annotations[0]->variable);
        $this->assertSame(false, isset($annotations[0]->comment));
        $this->assertSame('some\ns\ClassB', $annotations[1]->type);
        $this->assertSame('$cb', $annotations[1]->variable);
        $this->assertSame('With a comment', $annotations[1]->comment);
    }
}
