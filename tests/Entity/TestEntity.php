<?php

declare(strict_types=1);

namespace Objectiphy\Annotations\Tests\Entity;

use Objectiphy\Annotations\Tests\Annotations\Relationship as ObjectiphyRelationship;
use This\Silly\Ns;
use Another\Ns as OtherNamespace;
use My\Full\Classname as Another, \My\Full\NSname;
use TestEntity as AliasedTestEntity;
use Objectiphy as SomeWeirdRootAlias;
use Objectiphy\Annotations as YetAnotherWeirdOne;
use some\ns\


//Who would do a thing like this?
/* Blank lines and comments inside a use statement? */
/** Just don't. */

{
    ClassA, 
    // srsly
    
    ClassB, ClassC as C
};

/**
 * Class TestEntity
 * @package Objectiphy\Annotations\Tests\Entity
 * @Objectiphy\Annotations\Tests\Annotations\Table(name="test")
 */
class TestEntity
{
    /**
     * @var ClassB
     * @ObjectiphyRelationship(childClassName="ClassB",
     *     relationshipType="one_to_one"
     *
     * )
     */
    private ClassB $cb;

    /**
     * @var int $someProperty A property!
     * @random
     * @also_random Some words here $randomVariable More words here
     */
    private $someProperty;

    /**
     * @param string $someArg
     */
    public function someMethod(string $someArg)
    {
        echo $someArg;
    }

    /**
     * @param ClassB $cb
     * @param ClassB $cb With a comment
     */
    protected function otherMethod(ClassB $cb)
    {
        $this->cb = $cb;
    }
}
