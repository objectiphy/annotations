<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

//Conditional import - we won't force you to have Psr\SimpleCache installed
if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    class_alias(PsrSimpleCacheInterface::class, '\Psr\SimpleCache\CacheInterface');
}

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Cache decorator for the annotation reader. We intercept requests for annotations and try to get them from the cache 
 * if possible before deferring to the 'real' annotation reader.
 */
class CachedAnnotationReader implements AnnotationReaderInterface
{
    private AnnotationReaderInterface $annotationReader;
    private \Psr\SimpleCache\CacheInterface $cache;
    private string $keyPrefix = 'an';
    private array $annotations = [];

    public function __construct(AnnotationReaderInterface $annotationReader, \Psr\SimpleCache\CacheInterface $cache)
    {
        $this->annotationReader = $annotationReader;
        $this->cache = $cache;
    }

    /**
     * If you want to change the behaviour of exception handling after instantiation, you can call this setter.
     * Default is not to throw excpetions generally, only for Objectiphy annotations (since it is the wild west
     * out there, and we can only be sure an exception is a problem if we know the expectations).
     * @param bool $general Whether or not to throw exceptions.
     * @param bool $objectiphy Whether or not to throw exceptionns for Objectiphy annotations
     */
    public function setThrowExceptions(bool $general, bool $objectiphy = true): void
    {
        $this->annotationReader->setThrowExceptions($general, $objectiphy);
    }
    
    /**
     * Identify which attributes on the annotations we are reading refer to class names that might need to be expanded
     * (ie. where the class name specified in the annotation is relative to a use statement in the same file).
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void
    {
        $this->annotationReader->setClassNameAttributes($classNameAttributes);
        $this->keyPrefix = 'an' . substr(sha1(json_encode($classNameAttributes)), 0, 10);
    }

    /**
     * Returns an associative array of the attributes that were specified for a custom annotation. We need this because
     * it is impossible to tell otherwise whether a value was present in the annotation, or whether it is just the
     * default value for the object or was set separately.
     * @param string $className Name of class that holds the annotation
     * @param string $itemName 'p:' followed by property name, 'm:' followed by method name, or 'c' for a class
     * annotation.
     * @param string $annotationClassName Name of the custom annotation class whose attributes we want to return.
     * @return array Associative array of attributes.
     */
    public function getAttributesRead(string $className, string $itemName, string $annotationClassName): array
    {
        $delegate = function() use ($className, $itemName, $annotationClassName) {
            return $this->annotationReader->getAttributesRead($className, $itemName, $annotationClassName);
        };

        return $this->getFromCache($className, 'a#' . $annotationClassName . ':' . $itemName, $delegate);
    }

    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName): ?object
    {
        $delegate = function() use ($className, $annotationName) {
            return $this->annotationReader->getAnnotationFromClass($className, $annotationName);
        };

        return $this->getFromCache($className, 'c#' . $annotationName, $delegate);
    }

    /**
     * @param string $className Name of class that has the property whose annotation we want.
     * @param string $propertyName Name of property that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromProperty(string $className, string $propertyName, string $annotationName): ?object
    {
        $delegate = function() use ($className, $propertyName, $annotationName) {
            return $this->annotationReader->getAnnotationFromProperty($className, $propertyName, $annotationName);
        };
        
        return $this->getFromCache($className, 'p#' . $propertyName . '#' . $annotationName, $delegate);
    }

    /**
     * @param string $className Name of class that has the method whose annotation we want.
     * @param string $methodName Name of the method that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName): ?object
    {
        $delegate = function() use ($className, $methodName, $annotationName) {
            return $this->annotationReader->getAnnotationFromMethod($className, $methodName, $annotationName);
        };

        return $this->getFromCache($className, 'm#' . $methodName . '#' . $annotationName, $delegate);
    }

    /**
     * For Doctrine compatiblity only - get all annotations found on a class.
     * @param \ReflectionClass $class
     * @return array An array of annotation objects.
     */
    public function getClassAnnotations(\ReflectionClass $class)
    {
        $delegate = function() use ($class) {
            return $this->annotationReader->getClassAnnotations($class);
        };

        return $this->getFromCache($class->getName(), 'cm#' . $class->getName(), $delegate);
    }

    /**
     * For Doctrine compatiblity only - get a particular annotation from a class.
     * @param \ReflectionClass $class
     * @param $annotationName
     * @return object|null The annotation object.
     */
    public function getClassAnnotation(\ReflectionClass $class, $annotationName)
    {
        $delegate = function() use ($class, $annotationName) {
            return $this->annotationReader->getClassAnnotation($class, $annotationName);
        };
        
        return $this->getFromCache($class->getName(), 'c#' . $class->getName(), $delegate);
    }

    /**
     * For Doctrine compatiblity only - get all annotations found on a method.
     * @return array An array of annotation objects.
     */
    public function getMethodAnnotations(\ReflectionMethod $method)
    {
        $delegate = function() use ($method) {
            return $this->annotationReader->getMethodAnnotations($method);
        };
        
        return $this->getFromCache($method->getDeclaringClass()->getName(), 'mm#' . $method->getName(), $delegate);
    }

    /**
     * For Doctrine compatiblity only - get a particular annotation from a method.
     * @return object|null The annotation object.
     */
    public function getMethodAnnotation(\ReflectionMethod $method, $annotationName)
    {
        $delegate = function() use ($method, $annotationName) {
            return $this->annotationReader->getMethodAnnotation($method, $annotationName);
        };
        
        return $this->getFromCache(
            $method->getDeclaringClass()->getName(),
            'm#' . $method->getName() . '#' . $annotationName,
            $delegate
        );
    }

    /**
     * For Doctrine compatiblity only - get all annotations found on a property.
     * @return array An array of annotations objects.
     */
    public function getPropertyAnnotations(\ReflectionProperty $property)
    {
        $delegate = function() use ($property) {
            return $this->annotationReader->getPropertyAnnotations($property);
        };
        
        return $this->getFromCache($property->getDeclaringClass()->getName(), 'pm#' . $property->getName(), $delegate);
    }

    /**
     * For Doctrine compatiblity only - get a particular annotation from a property.
     * @return object|null The annotation object.
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName)
    {
        $delegate = function() use ($property, $annotationName) {
            return $this->annotationReader->getPropertyAnnotation($property, $annotationName);
        };
        
        return $this->getFromCache(
            $property->getDeclaringClass()->getName(),
            'p#' . $property->getName() . '#' . $annotationName,
            $delegate
        );
    }

    /**
     * @param string $className Name of class containing the annotation.
     * @param string $keyString Unique identifier for the annotation.
     * @param callable $delegate Callable to get the value if not cached.
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getFromCache(string $className, string $keyString, callable $delegate)
    {
        $loadedValue = $this->annotations[$className][$keyString] ?? null; //Do we already have it?
        if ($loadedValue === null) {
            $key = $this->keyPrefix . sha1($className . $keyString);
            //Load from cache
            $loadedValue = $this->cache->get($key, '**notfound**');
            if ($loadedValue === '**notfound**') {
                //If not in cache, load from delegate and save in cache
                $loadedValue = $delegate();
                $this->cache->set($key, $loadedValue);
            }
            //Either way, store value locally
            $this->annotations[$className][$keyString] = $loadedValue;
        }

        return $loadedValue;
    }
}
