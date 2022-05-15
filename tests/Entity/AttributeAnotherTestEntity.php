<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Entity;

use Objectiphy\Annotations\Tests\Annotations\Relationship as ObjectiphyRelationship;

class AttributeAnotherTestEntity
{
    #[ObjectiphyRelationship(relationshipType: 'one_to_many', joinColumn: 'join_col')]
    #[ObjectiphyRelationship(relationshipType: 'may_to_many', joinColumn: 'nonsense')]
    #[\Objectiphy\Annotations\Tests\Annotations\Relationship(relationshipType: 'many_to_one', joinColumnn: 'wth')]
    public function methodWithMultipleCustomAnnotations()
    {

    }
}
