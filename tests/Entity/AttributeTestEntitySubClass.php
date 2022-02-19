<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Entity;

class AttributeTestEntitySubClass extends TestEntity
{
    #[attr_var('int', '$meh')]
    private int $meh;
}
