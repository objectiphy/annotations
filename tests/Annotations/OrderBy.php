<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Annotations;

#[\Attribute]
class OrderBy
{
    public array $orderBy = [];
    
    public function __construct(array $orderBy)
    {
        $this->orderBy = $orderBy;
    }
}
