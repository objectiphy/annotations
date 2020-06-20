<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Interface for the annotation reader - indirectly extends the Doctrine one, if present.
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface AnnotationReaderInterface extends AnnotationReaderInterfaceBase
{
    /**
     * Identify which attributes on the annotations we are reading refer to class names that might need to be expanded
     * (ie. where the class name specified in the annotation is relative to a use statement in the same file).
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void;
    
    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName);

    /**
     * @param string $className Name of class that has the property whose annotation we want.
     * @param string $propertyName Name of property that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromProperty(string $className, string $propertyName, string $annotationName);

    /**
     * @param string $className Name of class that has the method whose annotation we want.
     * @param string $methodName Name of the method that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName);
}
