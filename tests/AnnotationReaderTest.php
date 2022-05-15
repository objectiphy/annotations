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
use Objectiphy\Annotations\Tests\Entity\AnotherTestEntity;
use Objectiphy\Annotations\Tests\Entity\AttributeTestEntity;
use Objectiphy\Annotations\Tests\Entity\AttributeTestEntitySubClass;
use Objectiphy\Annotations\Tests\Entity\AttributeTestNormalEntity;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use Objectiphy\Annotations\Tests\Annotations\Relationship;
use Objectiphy\Annotations\Tests\Annotations\Table;
use Objectiphy\Annotations\Tests\Entity\TestEntitySubClass;
use Objectiphy\Annotations\Tests\Entity\TestNormalEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

class AnnotationReaderTest extends TestCase
{
    private AnnotationReaderInterface $object;

    protected function setUp(): void
    {
        $this->object = new AnnotationReader(['childClassName', 'targetEntity']);
    }

    public function testGetAnnotationFromClass()
    {
        $classes = [TestEntity::class];
        if (\PHP_MAJOR_VERSION >= 8) {
            $classes[] = AttributeTestEntity::class;
        }
        foreach ($classes as $class) {
            $table = $this->object->getAnnotationFromClass($class, Table::class);
            $this->assertInstanceOf(Table::class, $table);
            $this->assertSame('test', $table->name);
        }

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
        $classes = [TestEntity::class];
        if (\PHP_MAJOR_VERSION >= 8) {
            $classes[] = AttributeTestEntity::class;
        }
        foreach ($classes as $class) {
            $relationship = $this->object->getAnnotationFromProperty($class, 'cb', Relationship::class);
            $this->assertInstanceOf(Relationship::class, $relationship);
            $this->assertSame('one_to_one', $relationship->relationshipType);
            $this->assertSame('some\ns\ClassB', $relationship->getChildClassName());

            //Unqualified
            $column = $this->object->getAnnotationFromProperty($class, 'unqualifiedAnnotation', Column::class);
            $this->assertInstanceOf(Column::class, $column);
            $this->assertSame('int', $column->type);
            $this->assertSame('some_column_or_other', $column->name);

            //Property does not exist
            $this->object->setThrowExceptions(false);
            $error = $this->object->getAnnotationFromProperty($class, 'madupProperty', Relationship::class);
            $this->assertNull($error);
            $this->assertStringContainsString('property', $this->object->lastErrorMessage);
        }

        $classes = [TestEntitySubClass::class];
        if (\PHP_MAJOR_VERSION >= 8) {
            $classes[] = AttributeTestEntitySubClass::class;
        }
        foreach ($classes as $class) {
            //Sub class, referring to a property on the super class
            $this->object->setClassNameAttributes([]);
            $relationship2 = $this->object->getAnnotationFromProperty($class, 'cb', Relationship::class);
            $this->assertSame('ClassB', $relationship2->getChildClassName());

            //Generic on the sub class
            $attrName = $class == AttributeTestEntitySubClass::class ? 'Objectiphy\Annotations\Tests\Entity\attr_var' : 'var';
            $var = $this->object->getAnnotationFromProperty($class, 'meh', $attrName);
            $this->assertSame(AnnotationGeneric::class, get_class($var));
            $this->assertSame('int', $var->type);
        }

        $classes = [TestNormalEntity::class];
        if (\PHP_MAJOR_VERSION >= 8) {
            $classes[] = AttributeTestNormalEntity::class;
        }
        foreach ($classes as $class) {
            //Serialization groups present
            $column = $this->object->getAnnotationFromProperty($class, 'product', Column::class);
            $this->assertEquals('product', $column->name);
            $group = $this->object->getAnnotationFromProperty($class, 'product', Groups::class);
            $this->assertEquals([0 => 'Default'], $group->getGroups());
        }
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
        $attributesRead = $this->object->getAttributesRead(TestEntity::class, 'c', Table::class);
        $this->assertArrayHasKey('name', $attributesRead);
        $this->assertSame('test', $attributesRead['name']);

        if (\PHP_MAJOR_VERSION >= 8) {
            $table = $this->object->getClassAnnotation(new \ReflectionClass(AttributeTestEntity::class), Table::class);
            $this->assertInstanceOf(Table::class, $table);
            $this->assertSame('test', $table->name);
            $attributesRead = $this->object->getAttributesRead(AttributeTestEntity::class, 'c', Table::class);
            $this->assertArrayHasKey('name', $attributesRead);
            $this->assertSame('test', $attributesRead['name']);
        }
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
        $this->assertSame(3, count($annotations));
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

    public function testMultipleCustomMethodAnnotations()
    {
        $reflectionMethod = new \ReflectionMethod(AnotherTestEntity::class, 'methodWithMultipleCustomAnnotations');
        $relationships = $this->object->getMethodAnnotation($reflectionMethod, Relationship::class);
        $this->assertSame(3, count($relationships));
        $this->assertInstanceOf(Relationship::class, $relationships[0]);
        $this->assertSame('one_to_many', $relationships[0]->relationshipType);
        $this->assertSame('join_col', $relationships[0]->joinColumn);
        $this->assertInstanceOf(Relationship::class, $relationships[1]);
        $this->assertSame('many_to_many', $relationships[1]->relationshipType);
        $this->assertSame('nonsense', $relationships[1]->joinColumn);
        $this->assertInstanceOf(Relationship::class, $relationships[2]);
        $this->assertSame('many_to_one', $relationships[2]->relationshipType);
        $this->assertSame('wth', $relationships[2]->joinColumn);
    }
}
