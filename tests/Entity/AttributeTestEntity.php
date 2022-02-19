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

#[Objectiphy\Annotations\Tests\Annotations\Table(name: 'test')]
class AttributeTestEntity
{
    /**
     * This will not resolve to an attribute/annnotation class as there is no use statement
     */
    #[Column(type: 'int', name: 'some_column_or_other')]
    protected $unqualifiedAnnotation;

    /**
     * @var ClassB
     */
    #[ObjectiphyRelationship(childClassName: ClassB::class, relationshipType: 'one_to_one')]
    #[Objectiphy\Annotations\Tests\Annotations\OrderBy(['one' => 'two', 'three' => 'four'])]
    private ClassB $cb;

    #[attrvar('int', '$someProperty', 'A property!')]
    #[random]
    #[also_random('Some words here', '$randomVariable', 'More words here')]
    private $someProperty;

    #[param('string', '$someArg')]
    public function someMethod(string $someArg)
    {
        echo $someArg;
    }

    #[param(ClassB::class, '$cb')]
    #[param(ClassB::class, '$cb', 'With a comment')]
    protected function otherMethod(ClassB $cb)
    {
        $this->cb = $cb;
    }
}
