<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\ClassAliasFinder;
use Objectiphy\Annotations\DocParser;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use Objectiphy\Objectiphy\Annotation\Relationship;
use PHPUnit\Framework\TestCase;

class DocParserTest extends TestCase
{
    private DocParser $object;
    
    protected function setUp(): void
    {
        $aliasFinder = new ClassAliasFinder();
        $this->object = new DocParser($aliasFinder);
    }
    
    public function testParseDocComment()
    {
        $reflectionClass = new \ReflectionClass(TestEntity::class);
        $docComment = '/** @param int $i Some random 
        * integer
        * @ObjectiphyRelationship(
        *         relationshipType = "one_to_one" ,
        *         childClass="YetAnotherWeirdOne\Tests\Entity\TestEntity", 
        *         lazyLoad=true,orderBy={"someProperty","OtherProperty"} 
        *     )
        */';
        $relationship = $this->object->getAnnotation($reflectionClass, $docComment, 'p#test', Relationship::class);
        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertSame('YetAnotherWeirdOne\Tests\Entity\TestEntity', $relationship->childClass);
        $this->assertSame(true, $relationship->lazyLoad);
        $this->assertIsArray($relationship->orderBy);
        $this->assertContains('someProperty', $relationship->orderBy);
        $this->assertContains('OtherProperty', $relationship->orderBy);

        $this->object->setClassNameAttributes(['childClass']);
        $relationship2 = $this->object->getAnnotation($reflectionClass, $docComment, 'p#test', Relationship::class);
        $this->assertInstanceOf(Relationship::class, $relationship2);
        $this->assertSame(TestEntity::class, $relationship2->childClass);

        $intAnnotation = $this->object->getAnnotation($reflectionClass, $docComment, 'p#int', 'param');
        $this->assertSame("int \$i Some random \n integer", str_replace('  ', '', $intAnnotation->value));
    }
}
