<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests;

use Objectiphy\Annotations\ClassAliasFinder;
use Objectiphy\Annotations\DocParser;
use Objectiphy\Annotations\Tests\Entity\TestEntity;
use Objectiphy\Annotations\Tests\Annotations\Relationship;
use PHPUnit\Framework\TestCase;

class DocParserTest extends TestCase
{
    private DocParser $object;
    
    protected function setUp(): void
    {
        $aliasFinder = new ClassAliasFinder();
        $this->object = new DocParser($aliasFinder);
    }
    
    public function testGetClassAnnotations()
    {
        $this->assertSame(true, true);
    }

    public function testGetPropertyAnnotations()
    {
        $this->assertSame(true, true);
    }

    public function testGetMethodAnnotations()
    {
        $this->assertSame(true, true);
    }
}
