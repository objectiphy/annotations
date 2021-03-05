<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for the annotation reader - indirectly extends the Doctrine one, if present.
 */
interface AnnotationReaderInterface extends AnnotationReaderInterfaceBase
{
    /**
     * If you want to change the behaviour of exception handling after instantiation, you can call this setter.
     * Default is not to throw excpetions generally, only for Objectiphy annotations (since it is the wild west
     * out there, and we can only be sure an exception is a problem if we know the expectations).
     * @param bool $general Whether or not to throw exceptions.
     * @param bool $objectiphy Whether or not to throw exceptionns for Objectiphy annotations
     */
    public function setThrowExceptions(bool $general, bool $objectiphy = true): void;
    
    /**
     * Identify which attributes on the annotations we are reading refer to class names that might need to be expanded
     * (ie. where the class name specified in the annotation is relative to a use statement in the same file).
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void;

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
    public function getAttributesRead(string $className, string $itemName, string $annotationClassName): array;

    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName);

    /**
     * @param string $className Name of class that has the property whose annotation we want.
     * @param string $propertyName Name of property that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromProperty(string $className, string $propertyName, string $annotationName);

    /**
     * @param string $className Name of class that has the method whose annotation we want.
     * @param string $methodName Name of the method that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName);
}
