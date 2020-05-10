<?php
/**
 * This is a slightly unorthodox test, just to make sure that the examples given in the documentation do work as stated.
 */
declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use PHPUnit\Framework\TestCase;
use Objectiphy\Annotations\AnnotationReader;
use MyAnnotation as AnnotationAlias;
use MyNamespace\ValueObjects\OtherClass;

class MyEntity
{
    /** @var MyEntity $childObject A child object of the same type as the parent. */
    private MyEntity $childObject;
}

class MyEntity2
{
    /**
     * @var OtherClass
     * @AnnotationAlias(name="nameValue", childClassName="OtherClass")
     */
    public $childClass;
}

class MyAnnotation
{
    public string $name;
    public string $childClassName;
    public int $value = 100;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

class DocumentationExampleTest extends TestCase
{
    public function testGeneric()
    {
        $annotationReader = new AnnotationReader();
        $annotation = $annotationReader->getAnnotationFromProperty(MyEntity::class, 'childObject', 'var');

        ob_start();
        echo "Name: " . $annotation->name . "\n";
        echo "Type: " . $annotation->type . "\n";
        echo "Variable: " . $annotation->variable . "\n";
        echo "Comment: " . $annotation->comment;
        $output = ob_get_clean();

        $this->assertSame('Name: var
Type: Objectiphy\Annotations\Tests\MyEntity
Variable: $childObject
Comment: A child object of the same type as the parent.', $output);
    }

    public function testCustom()
    {
        $annotationReader = new AnnotationReader();
        $annotationReader->setClassNameAttributes(['childClassName']);
        $annotation = $annotationReader->getAnnotationFromProperty(MyEntity2::class, 'childClass', MyAnnotation::class);

        ob_start();
        echo "Name: " . $annotation->name . "\n";
        echo "Child Class Name: " . $annotation->childClassName . "\n";
        echo "Value: " . $annotation->value;
        $output = ob_get_clean();

        $this->assertSame('Name: nameValue
Child Class Name: MyNamespace\ValueObjects\OtherClass
Value: 100', $output);
    }
}
