# Objectiphy Annotations

## Description
A standalone attribute and annotation reader that reads attributes and 
parses annotations in PHP doc comments. Compatible with Doctrine, but 
does not require it, ie. it can be used in place of the Doctrine annotation 
reader, as long as you don't need nested annotations on a class (nested 
annotations on properties and methods are OK in docblock annotations but
not attributes). Nested class annotations are not supported because 
Objectiphy does not need them, and native PHP 8 attributes do not support 
nesting.

## Why not just use Doctrine?

No reason! By all means, use Doctrine - it is great. I wrote this partly 
as an academic exercise, but also to give me more freedom to do what I 
want. At the time of writing, Doctrine makes you jump through a few 
hoops and is not very tolerant of random non-standard annotations that 
you have not told it about. I think this is a little easier to use, and 
it should perform just as well as the Doctrine one. You can read any 
attribute or annotation with this reader (except nested annotations on a 
class).

## Requirements

Objectiphy Annotations requires PHP 7.4 or higher. It has no other 
dependencies. I chose PHP 7.4 because that was the latest version at 
time of initial writing, and allowed me to use type hints on properties
which earlier versions of PHP did not support. It has been updated to
read attributes in PHP 8 and beyond.

## Installation

You can install Objectiphy Annotations with composer:
```
composer require objectiphy/annotations
```
...or just git clone or download the project and include it directly or 
with a PSR-4 autoloader.

## Basic usage

The following documentation describes docblock annotations, but the equivalent
PHP 8 attributes will also work in the same way. For example, whereas an
annotation might look like this:

```php
/**
 * @Mapping\Relationship(
 *    childClassName="TestUser",
 *    sourceJoinColumn="user_id", 
 *    relationshipType="one_to_one", 
 *    cascadeDeletes=true,
 *    orphanRemoval=true
 * )
 */
```

...the equivalent attribute would look like this:

```php
#[Mapping\Relationship(
    childClassName: TestUser::class, 
    sourceJoinColumn: 'user_id', 
    relationshipType: 'one_to_one', 
    cascadeDeletes: true, 
    orphanRemoval: true
)]
```

...and both of the above would be read and returned by the annotation reader
in exactly the same way.

Suppose you have an entity with an annotation on a property, like this:

```php
namespace MyNamespace;

class MyEntity
{
    /** @var MyEntity $childObject A child object of the same type as the parent. */
    private MyEntity $childObject;
}
```

You can create an annotation reader and read the `@var` annotation like this (note that in most cases you should use a dependency injection container to create the reader rather than instantiating it directly):

```php
use Objectiphy\Annotations\AnnotationReader;
use MyNamespace\MyEntity;

$annotationReader = new AnnotationReader();
$annotation = $annotationReader->getAnnotationFromProperty(MyEntity::class, 'childObject', 'var');

echo "Name: " . $annotation->name . "\n";
echo "Type: " . $annotation->type . "\n";
echo "Variable: " . $annotation->variable . "\n";
echo "Comment: " . $annotation->comment;
```
The above code would output:

```
Name: var
Type: MyNamespace\MyEntity
Variable: $childObject
Comment: A child object of the same type as the parent.
```

Note that the type has been resolved as a fully qualified class name. The reader will attempt to resolve class names in generic annotations like this if there is a single word following the annotation name and nothing else, or if there is a single word after the annotation name followed by a word that starts with a dollar sign (which is assumed to be a variable).

## Usage with custom annotation classes
You can also use custom annotation classes, and the annotation reader will attempt to return an instance of your class.
You don't have to tell the reader about your class, or register any namespaces, or use any annotations on it. 

For example, if you have a class with a mandatory constructor argument, a public property, and a protected property with a getter and setter like this:

```php
namespace MyNamespace\Annotations;

class MyAnnotation
{
    public string $childClassName;
    protected int $value = 100;
    private string $name;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function setValue(int $value): void
    {
        $this->value = $value;
    }
    
    public function getValue(): int
    {
        return $this->value;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
}
```

...you can use it as an annotation on a class, property, or method, like
this:

```php
namespace MyNamespace\Entities;

//You don't have to use an alias, this is just to demonstrate that you can:
use MyNamespace\Annotations\MyAnnotation as AnnotationAlias;
use MyNamespace\ValueObjects\OtherClass;

class MyEntity2
{
    /**
     * @var OtherClass
     * @AnnotationAlias(name="nameValue", childClassNameName="OtherClass", value=200)
     */
    public $childClassName;
}
```

...and use the annotation reader to resolve the annotation into an 
instance of your custom annotation class, like this:

```php
use Objectiphy\Annotations\AnnotationReader;
use MyNamespace\Annotations\MyAnnotation;
use MyNamespace\Entities\MyEntity2;

$annotationReader = new AnnotationReader();
$annotationReader->setClassNameAttributes(['childClassName']);
$annotation = $annotationReader->getAnnotationFromProperty(MyEntity2::class, 'childClassName', MyAnnotation::class);

echo "Name: " . $annotation->getName() . "\n";
echo "Child Class Name: " . $annotation->childClassName . "\n";
echo "Value: " . $annotation->getValue();
```

...which would output the following (note that because we told it that 
`childClassNameName` is a class name attribute, it went ahead and resolved that to a fully qualified class name):

```
Name: nameValue
Child Class Name: MyNamespace\ValueObjects\OtherClass
Value: 200
```

When populating your object, the annotation reader will check to see if 
there are any mandatory constructor arguments, and will pass any 
matching values into the constructor. It will then go through all of 
the defined attributes, and if there is a matching property name, it 
will set that property to the value of the attribute (using a setter if 
the property is not public and there is a method with a matching name 
prefixed with 'set').

## Using the interface

The annotation reader implements AnnotationReaderInterface, which 
extends the Doctrine Reader interface if it exists. You can therefore 
pass an instance of AnnotationReader to any service that requires the 
Doctrine Reader interface. 

When type-hinting for an annotation reader in your own code, you should 
always hint on `AnnotationReaderInterface` (or Doctrine's `Reader`) - 
do not hint on `AnnotationReader` itself. This will allow you (for 
example) to later swap out the concrete implementation to a cached 
reader (see Caching section, below).

## Silent operation

As there are no rules governing how annotations should be unserialized 
into objects, there might be cases where the reader cannot create the 
expected object. By default, this will fail silently UNLESS it relates 
to an Objectiphy annotation (in which case we know what the rules are, 
so exceptions are exceptions). If any errors occur while in silent 
mode, the `$lastErrorMessage` property will be populated, and a value 
of `null` will be returned, but no exception will be thrown.

To get it to throw exceptions for non-Objectiphy annotations, just set 
the `$throwExceptions` argument to true in the constructor when 
creating an AnnotationReader instance. To suppress exceptions for 
Objectiphy annotations, set the `$throwExceptionsObjectiphy` flag to 
false. 

## Caching

You can use any PSR-16 compatible caching mechanism to cache 
annotations. Using a cache can reduce the amount of processing needed 
to read annotations, which might be an important consideration in a 
scalable environment such as AWS, although there is still an overhead 
involved in reading from and writing to a cache, which could negate any 
performance benefits for simple use cases.

To use a cache, simply instantiate a CachedAnnotationReader and pass 
an instance of your PSR-16 cache and a standard AnnotationReader to it. 
CachedAnnotationReader is a decorator for the standard AnnotationReader 
class, and implements the same AnnotationReaderInterface.

## Credits

Developed by Russell Walker ([rwalker.php@gmail.com](mailto:rwalker.php@gmail.com?subject=Objectiphy%20Annotations))

## Licence

Objectiphy Annotations is released under the MIT licence - see enclosed 
licence file.
