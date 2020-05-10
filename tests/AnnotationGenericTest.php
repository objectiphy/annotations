<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\AnnotationGeneric;
use Objectiphy\Annotations\ClassAliasFinder;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use PHPUnit\Framework\TestCase;

class AnnotationGenericTest extends TestCase
{
    /**
     * @dataProvider annotationDataProvider
     */
    public function testAnnotationHydration(
        string $annotationName,
        string $annotationValue,
        array $expectedParts
    ) {
        $aliasFinder = new ClassAliasFinder();
        $closure = function($alias) use ($aliasFinder) {
            $reflectionClass = new \ReflectionClass(TestEntity::class);
            return $aliasFinder->findClassForAlias($reflectionClass, $alias, false);
        };
        $generic = new AnnotationGeneric($annotationName, $annotationValue, $closure);
        foreach ($expectedParts as $key => $value) {
            $this->assertSame($value, $generic->$key);
        }
    }

    /**
     * [$annotationName, $annotationValue, [$expectedParts]]
     */
    public function annotationDataProvider()
    {
        return [
            //@var int $someProperty A property!
            [
                'var',
                'int $someProperty A property!',
                [
                    'name' => 'var',
                    'value' => 'int $someProperty A property!',
                    'type' => 'int',
                    'variable' => '$someProperty',
                    'comment' => 'A property!',
                ]
            ],

            //@param ClassB $cb With a comment
            [
                'param',
                'ClassB $cb With a comment',
                [
                    'name' => 'param',
                    'value' => 'ClassB $cb With a comment',
                    'type' => 'some\ns\ClassB',
                    'variable' => '$cb',
                    'comment' => 'With a comment',
                ]
            ],

            //@some_random_annotation Some random comment
            [
                'some_random_annotation',
                'Some random comment',
                [
                    'name' => 'some_random_annotation',
                    'value' => 'Some random comment',
                    'comment' => 'Some random comment',
                ]
            ],

            //@what_the_hell_is_this Several words here $variableName
            [
                'what_the_hell_is_this',
                'Several words here $variableName',
                [
                    'name' => 'what_the_hell_is_this',
                    'value' => 'Several words here $variableName',
                    'preVariableParts' => [
                        'part_1' => 'Several',
                        'part_2' => 'words',
                        'part_3' => 'here',
                    ],
                    'variable' => '$variableName',
                ]
            ],

            //@var ClassB
            [
                'var',
                'ClassB',
                [
                    'name' => 'var',
                    'value' => 'ClassB',
                    'type' => 'some\ns\ClassB',
                ]
            ],
        ];
    }
}
