<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Annotations;

#[Attribute]
class OrderBy
{
    public function __construct(array $orderBy)
    {

    }
}
