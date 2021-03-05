<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Entity;

use Objectiphy\Annotations\Tests\Annotations\Column;
use Symfony\Component\Serializer\Annotation\Groups;

class TestNormalEntity
{
    /**
     * @Column(name="product", type="string")
     * @Groups({"Default"})
     */
    protected string $product;
}
