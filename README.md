# Objectiphy Annotations

## Description
A standalone annotation reader that reads and parses annotations in PHP doc comments. Compatible with Doctrine, but does not require it (ie. it can be used in place of the Doctrine annotation reader, but it is perhaps a bit more flexible).

## Why not just use Doctrine?

No reason! By all means, use Doctrine - it is great. I wrote this partly as an academic exercise, but also to give me more freedom to do what I want. At the time of writing, Doctrine makes you jump through a few hoops and is not very tolerant of random non-standard annotations that you have not told it about. I think this is a little easier to use, and it should perform just as well as the Doctrine one.

## Requirements

Objectiphy Annotations requires PHP 7.4 or higher. It has no other dependencies. I chose PHP 7.4 because that is the latest version at time of writing, and allows me to use type hints on properties which earlier versions of PHP did not support.

## Installation

You can install Objectiphy Annotations with composer:
```
composer require objectiphy/annotations
```
...or just git clone or download the project and include it directly.

## Basic usage

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

## Using the interface

The annotation reader implements AnnotationReaderInterface, which extends the Doctrine Reader interface if it exists. You can therefore pass an instance of AnnotationReader to any service that requires the Doctrine Reader interface. 

When type-hinting for an annotation reader in your own code, you should always hint on `AnnotationReaderInterface` (or Doctrine's `Reader`) - do not hint on `AnnotationReader` itself. This will allow you (for example) to later swap out the concrete implementation to a cached reader (see Caching section, below).

## Usage with custom annotation classes
You can also use custom annotation classes, and the annotation reader will attempt to return an instance of your class.
You don't have to tell the reader about your class, or register any namespaces, or use any annotations on it. 

For example, if you have a class with a mandatory constructor argument, like this:

```php
namespace MyNamespace\Annotations;

class MyAnnotation
{
    public string $name;
    public string $childClassNameName;
    public int $value = 100;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
```

...you can use it as an annotation on a class, property, or method, like this:

```php
namespace MyNamespace\Entities;

//You don't have to use an alias, this is just to demonstrate that you can:
use MyNamespace\Annotations\MyAnnotation as AnnotationAlias;
use MyNamespace\ValueObjects\OtherClass;

class MyEntity2
{
    /**
     * @var OtherClass
     * @AnnotationAlias(name="nameValue", childClassNameName="OtherClass")
     */
    public $childClassName;
}
```

...and use the annotation reader to resolve the annotation into an instance of your custom annotation class, like this:

```php
use Objectiphy\Annotations\AnnotationReader;
use MyNamespace\Annotations\MyAnnotation;
use MyNamespace\Entities\MyEntity2;

$annotationReader = new AnnotationReader();
$annotationReader->setClassNameAttributes(['childClassNameName']);
$annotation = $annotationReader->getAnnotationFromProperty(MyEntity2::class, 'childClassName', MyAnnotation::class);

echo "Name: " . $annotation->name . "\n";
echo "Child Class Name: " . $annotation->childClassNameName . "\n";
echo "Value: " . $annotation->value;
```

...which would output the following (note that because we told it that `childClassNameName` is a class name attribute, it went ahead and resolved that to a fully qualified class name):

```
Name: nameValue
Child Class Name: MyNamespace\ValueObjects\OtherClass
Value: 100
```

When populating your object, the annotation reader will check to see if there are any mandatory constructor arguments, and will pass any matching values into the constructor. It will then go through all of the defined attributes, and if there is a matching property name, it will set that property to the value of the attribute.

## Silent operation

In most cases, if you ask the annotation reader to do something that does not make sense (eg. to create a class that does not exist), you would expect it to throw an exception - which it does. However, there might be times when you don't want this to happen (eg. when using the annotation reader in a command-line script where failures should be ignored).

To switch to silent mode, just set the `$throwExceptions` argument to false in the constructor when creating an AnnotationReader instance. If any errors occur while in silent mode, the `$lastErrorMessage` property will be populated, and a value of `null` will be returned, but no exception will be thrown.

## Caching

You can use any PSR-16 compatible caching mechanism to cache annotations. Using a cache can reduce the amount of processing needed to read annotations, which might be an important consideration in a scalable environment such as AWS, although there is still an overhead involved in reading from and writing to a cache, which could negate any performance benefits for simple use cases.

To use a cache, simply instantiate a CachedAnnotationReader and pass an instance of your PSR-16 cache and a standard AnnotationReader to it. CachedAnnotationReader is a decorator for the standard AnnotationReader class, and implements the same AnnotationReaderInterface.

## Credits

Developed by Russell Walker ([rwalker.php@gmail.com](mailto:rwalker.php@gmail.com?subject=Objectiphy%20Annotations))

## Licence

Objectiphy Annotations is released under the MIT licence - see enclosed licence file.
