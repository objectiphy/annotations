<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Entity;

use Objectiphy\Annotations\Tests\Annotations\Relationship as ObjectiphyRelationship;

class AnotherTestEntity
{
    /**
     * @ObjectiphyRelationship(relationshipType="one_to_many", joinColumn="join_col")
     * @ObjectiphyRelationship(relationshipType="many_to_many", joinColumn="nonsense")
     * @Objectiphy\Annotations\Tests\Annotations\Relationship(relationshipType="many_to_one", joinColumn="wth")
     */
    public function methodWithMultipleCustomAnnotations()
    {

    }
}
