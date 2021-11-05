<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Annotations\CachedAnnotationReader;
use Objectiphy\Annotations\PsrSimpleCacheInterface;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use Objectiphy\Objectiphy\Annotation\Relationship;
use Objectiphy\Objectiphy\Annotation\Table;
use PHPUnit\Framework\TestCase;

//Conditional import - we won't force you to have Psr\SimpleCache installed
if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    class_alias(PsrSimpleCacheInterface::class, '\Psr\SimpleCache\CacheInterface');
}

class CachedAnnotationReaderTest extends TestCase
{
    private \Psr\SimpleCache\CacheInterface $cache;
    private AnnotationReaderInterface $delegate;
    private AnnotationReaderInterface $object;

    protected function setUp(): void
    {
        $this->cache = $this->getMockBuilder(\Psr\SimpleCache\CacheInterface::class)->getMock();
        $this->delegate = $this->getMockBuilder(AnnotationReaderInterface::class)->disableOriginalConstructor()->getMock();
        $this->object = new CachedAnnotationReader($this->delegate, $this->cache);
    }

    /**
     * @dataProvider provider
     */
    public function testCachedRead(string $methodName, array $args, string $cacheKey, array $classNameAttributes = [])
    {
        if (!empty($classNameAttributes)) {
            $this->object->setClassNameAttributes($classNameAttributes);
        }

        //Uncached
        $delegateResult = new \stdClass();
        $delegateResult->name = 'delegate';
        $this->delegate->expects($this->once())
            ->method($methodName)
            ->with(...$args)
            ->will($this->returnValue($delegateResult));
        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->will($this->returnValue('**notfound**'));
        $this->cache->expects($this->once())
            ->method('set')
            ->with($cacheKey);
        $result = $this->object->$methodName(...$args);
        $this->assertSame('delegate', $result->name);

        //In memory
        $result2 = $this->object->$methodName(...$args);
        $this->assertSame('delegate', $result2->name);

        //From cache
        $this->setUp(); //Clear memory
        $cachedResult = new \stdClass();
        $cachedResult->name = 'cached';
        if (!empty($classNameAttributes)) {
            $this->object->setClassNameAttributes($classNameAttributes);
        }
        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->will($this->returnValue($cachedResult));
        $result3 = $this->object->$methodName(...$args);
        $this->assertSame('cached', $result3->name);

        //In memory
        $result4 = $this->object->$methodName(...$args);
        $this->assertSame('cached', $result4->name);
    }

    public function provider()
    {
        return array_merge($this->getData(), $this->getData(true));
    }

    private function getData(bool $withClassNameAttributes = false)
    {
        $keyPrefix = 'an' . ($withClassNameAttributes ? substr(sha1(json_encode(['childClassName'])), 0, 10) : '');

        $data = [
            [
                'methodName' => 'getAnnotationFromClass',
                'args' => [TestEntity::class, Table::class],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'c#' . Table::class),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getAnnotationFromProperty',
                'args' => [TestEntity::class, 'cb', Relationship::class],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'p#cb#' . Relationship::class),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getAnnotationFromMethod',
                'args' => [TestEntity::class, 'someMethod', 'param'],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'm#someMethod#param'),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getClassAnnotation',
                'args' => [new \ReflectionClass(TestEntity::class), Table::class],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'c#' . TestEntity::class),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getPropertyAnnotation',
                'args' => [new \ReflectionProperty(TestEntity::class, 'cb'), Relationship::class],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'p#cb#' . Relationship::class),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getMethodAnnotation',
                'args' => [new \ReflectionMethod(TestEntity::class, 'someMethod'), 'param'],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'm#someMethod#param'),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getClassAnnotations',
                'args' => [new \ReflectionClass(TestEntity::class)],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'cm#' . TestEntity::class),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getPropertyAnnotations',
                'args' => [new \ReflectionProperty(TestEntity::class, 'cb')],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'pm#cb'),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ],
            [
                'methodName' => 'getMethodAnnotations',
                'args' => [new \ReflectionMethod(TestEntity::class, 'someMethod')],
                'cacheKey' => $keyPrefix . sha1(TestEntity::class . 'mm#someMethod'),
                'classNameAttributes' => $withClassNameAttributes ? ['childClassName'] : [],
            ]
        ];

        return $data;
    }
}
