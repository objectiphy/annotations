<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Entry point to allow reading of annotations on classes, properties, and methods.
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class AnnotationReader implements AnnotationReaderInterface
{
    /** @var string In case we are in silent mode, any error messages will be reported here. */
    public string $lastErrorMessage = '';
    private bool $throwExceptions;

    private ?\ReflectionClass $reflectionClass = null;
    private string $class = ''; //Just makes code more concise instead of grabbing from $reflectionClass all the time
    private DocParser $docParser;
    private AnnotationResolver $resolver;

    //Local cache
    private array $resolvedClassAnnotations = [];
    private array $resolvedPropertyAnnotations = [];
    private array $resolvedMethodAnnotations = [];

    /**
     * @param array $classNameAttributes If any attributes of the annotation need to be resolved to fully qualified
     * class names, specify the attribute names here.
     * @param bool $throwExceptions For silent operation, set to false, and any errors will be ignored, and annotations
     * that could not be parsed will be returned as null. Either way, the lastErrorMessage property will be populated
     * with any exception messages.
     * @param DocParser $docParser
     * @param AnnotationResolver $resolver
     */
    public function __construct(
        array $classNameAttributes = [],
        $throwExceptions = true,
        DocParser $docParser = null,
        AnnotationResolver $resolver = null
    ) {
        $this->setThrowExceptions($throwExceptions);
        $this->docParser = $docParser ?? new DocParser();
        $this->resolver = $resolver ?? new AnnotationResolver();
        $this->setClassNameAttributes($classNameAttributes);
    }

    /**
     * Pass it along and clear the local cache
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void
    {
        $this->resolver->setClassNameAttributes($classNameAttributes);
        $this->resolvedClassAnnotations = [];
        $this->resolvedPropertyAnnotations = [];
        $this->resolvedMethodAnnotations = [];
    }

    /**
     * If you want to change the behaviour of exception handling after instantiation, you can call this setter.
     * @param bool $value Whether or not to throw exceptions.
     */
    public function setThrowExceptions(bool $value): void
    {
        $this->throwExceptions = $value;
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
        return $this->resolver->getAttributesRead($className, $itemName, $annotationClassName);
    }

    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @return array|object|null
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->setClass($className);
            return $this->getClassAnnotation($this->reflectionClass, $annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * @param string $className Name of class that has the property whose annotation we want.
     * @param string $propertyName Name of property that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromProperty(string $className, string $propertyName, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->setClass($className);
            while ($this->reflectionClass && !$this->reflectionClass->hasProperty($propertyName)) {
                $this->setClass('', $this->reflectionClass->getParentClass() ?: null);
            }
            if ($this->reflectionClass && $this->reflectionClass->hasProperty($propertyName)) {
                $reflectionProperty = $this->reflectionClass->getProperty($propertyName);
                return $this->getPropertyAnnotation($reflectionProperty, $annotationName);
            } else {
                $errorMessage = sprintf('Class %1$s does not have a property named %2$s.', $className, $propertyName);
                throw new AnnotationReaderException($errorMessage);
            }
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * @param string $className Name of class that has the method whose annotation we want.
     * @param string $methodName Name of the method that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object|array|null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->setClass($className);
            while ($this->reflectionClass && !$this->reflectionClass->hasMethod($methodName)) {
                $this->setClass('', $this->reflectionClass->getParentClass() ?: null);
            }
            if ($this->reflectionClass && $this->reflectionClass->hasMethod($methodName)) {
                $reflectionMethod = $this->reflectionClass->getMethod($methodName);
                return $this->getMethodAnnotation($reflectionMethod, $annotationName);
            } else {
                $errorMessage = sprintf('Class %1$s does not have a method named %2$s.', $className, $methodName);
                throw new AnnotationReaderException($errorMessage);
            }
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /******************************************************************************************************************
     * The following public methods are here for compatibility with the Doctrine Reader interface. If you need to pass
     * a Doctrine Reader to some other service, you can use an instance of this class insetad of the Doctrine reader 
     * (just seemed like a cool thing to support, probably useless in real life though!). These methods are also called
     * internally from the other public methods to keep things DRY.
     *****************************************************************************************************************/

    /**
     * For Doctrine compatibility (therefore cannot typehint scalar parameters or return type).
     * @param \ReflectionClass $class
     * @param string $annotationName
     * @return object|null
     * @throws AnnotationReaderException
     */
    public function getClassAnnotation(\ReflectionClass $class, $annotationName)
    {
        try {
            $this->setClass('', $class);
            return $this->resolveClassAnnotation($annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * For Doctrine compatibility (therefore cannot typehint scalar parameters or return type).
     * @param \ReflectionProperty $property
     * @param string $annotationName
     * @return object|null
     * @throws AnnotationReaderException
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName)
    {
        try {
            $this->setClass('', $property->getDeclaringClass());
            return $this->resolvePropertyAnnotation($property->getName(), $annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * For Doctrine compatibility (therefore cannot typehint scalar parameters or return type).
     * @param \ReflectionMethod $method
     * @param $annotationName
     * @return object|null
     * @throws AnnotationReaderException
     */
    public function getMethodAnnotation(\ReflectionMethod $method, $annotationName)
    {
        try {
            $this->setClass('', $method->getDeclaringClass());
            return $this->resolveMethodAnnotation($method->getName(), $annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * For Doctrine compatibility.
     * @param \ReflectionClass $class
     * @return array Indexed array.
     * @throws \Exception
     */
    public function getClassAnnotations(\ReflectionClass $class)
    {
        try {
            $this->setClass('', $class);
            return $this->unifiedArrayValues($this->resolveClassAnnotations());
        } catch (\Exception $ex) {
            return $this->handleException($ex, true);
        }
    }

    /**
     * For Doctrine compatibility.
     * @param \ReflectionProperty $property
     * @return array Indexed array.
     */
    public function getPropertyAnnotations(\ReflectionProperty $property)
    {
        try {
            $this->setClass('', $property->getDeclaringClass());
            return $this->unifiedArrayValues($this->resolvePropertyAnnotations($property->getName()));
        } catch (\Exception $ex) {
            return $this->handleException($ex, true);
        }
    }

    /**
     * For Doctrine compatibility.
     * @param \ReflectionMethod $method
     * @return array Indexed array.
     */
    public function getMethodAnnotations(\ReflectionMethod $method)
    {
        try {
            $this->setClass('', $method->getDeclaringClass());
            return $this->unifiedArrayValues($this->resolveMethodAnnotations($method->getName()));
        } catch (\Exception $ex) {
            return $this->handleException($ex, true);
        }
    }

    /**
     * Given an associative array, which might contain indexed arrays, combine into one indexed array. For example:
     * [
     *   'param' => [
     *     0 => 'value1',
     *     1 => 'value2'
     *   ],
     *   'var' => 'value3',
     *   'something_else' => [
     *     0 => 'value4'
     *   ]
     * ]
     *
     * ...would return:
     *
     * [
     *   0 => 'value1',
     *   1 => 'value2',
     *   2 => 'value3',
     *   3 => 'value4'
     * ]
     * @param array $array
     */
    private function unifiedArrayValues(array $array): array
    {
        $return = [];
        foreach ($array as $arrayValue) {
            if (is_array($arrayValue)) {
                $return = array_merge($return, $arrayValue);
            } else {
                $return[] = $arrayValue;
            }
        }

        return $return;
    }

    /******************************************************************************************************************
     * End of Doctrine compatibility methods.
     *****************************************************************************************************************/

    /**
     * Store the host class name and reflection (can be null)
     * @param string $className
     * @param \ReflectionClass|null $reflectionClass
     * @throws \ReflectionException
     */
    private function setClass(string $className = '', ?\ReflectionClass $reflectionClass = null): void
    {
        if (!$reflectionClass && $className) {
            $reflectionClass = new \ReflectionClass($className);
        }
        $this->reflectionClass = $reflectionClass;
        $this->class = $reflectionClass ? $reflectionClass->getName() : '';
    }

    /**
     * Defer to the parser and resolver
     */
    private function resolveClassAnnotations(): array
    {
        if (empty($this->resolvedClassAnnotations[$this->class])) {
            $this->resolvedClassAnnotations[$this->class] = [];
            $annotations = $this->docParser->getClassAnnotations($this->reflectionClass);
            foreach ($annotations ?? [] as $index => $nameValuePair) {
                foreach ($nameValuePair as $name => $value) {
                    $resolved = $this->resolver->resolveClassAnnotation($this->reflectionClass, $name, $value);
                    $this->addResolvedToIndex($this->resolvedClassAnnotations[$this->class], $name, $resolved);
                }
            }
        }

        return $this->resolvedClassAnnotations[$this->class];
    }

    private function resolvePropertyAnnotations(string $propertyName): array
    {
        if (empty($this->resolvedPropertyAnnotations[$this->class][$propertyName])) {
            $this->resolvedPropertyAnnotations[$this->class][$propertyName] = [];
            $annotations = $this->docParser->getPropertyAnnotations($this->reflectionClass);
            if (!empty($annotations[$propertyName])) {
                foreach ($annotations[$propertyName] as $nameValuePair) {
                    foreach ($nameValuePair as $name => $value) {
                        $resolved = $this->resolver->resolvePropertyAnnotation($this->reflectionClass, $propertyName, $name, $value);
                        $this->addResolvedToIndex($this->resolvedPropertyAnnotations[$this->class][$propertyName], $name, $resolved);
                    }
                }
            }
        }

        return $this->resolvedPropertyAnnotations[$this->class][$propertyName];
    }

    private function resolveMethodAnnotations(string $methodName): array
    {
        if (empty($this->resolvedMethodAnnotations[$this->class][$methodName])) {
            $this->resolvedMethodAnnotations[$this->class][$methodName] = [];
            $annotations = $this->docParser->getMethodAnnotations($this->reflectionClass);
            if (!empty($annotations[$methodName])) {
                foreach ($annotations[$methodName] as $nameValuePair) {
                    foreach ($nameValuePair as $name => $value) {
                        $resolved = $this->resolver->resolveMethodAnnotation($this->reflectionClass, $methodName, $name, $value);
                        $this->addResolvedToIndex($this->resolvedMethodAnnotations[$this->class][$methodName], $name, $resolved);
                    }
                }
            }
        }
        return $this->resolvedMethodAnnotations[$this->class][$methodName];
    }

    /**
     * @param string $annotationName
     * @return object|array|null
     */
    private function resolveClassAnnotation(string $annotationName)
    {
        $resolvedAnnotations = $this->resolveClassAnnotations();
        return $resolvedAnnotations[$annotationName] ?? null;
    }

    /**
     * @param string $propertyName
     * @param string $annotationName
     * @return object|array|null
     */
    private function resolvePropertyAnnotation(string $propertyName, string $annotationName)
    {
        $this->resolvePropertyAnnotations($propertyName);
        $this->resolveUnqualified($this->resolvedPropertyAnnotations[$this->class][$propertyName], $annotationName);
        return $this->resolvedPropertyAnnotations[$this->class][$propertyName][$annotationName] ?? null;
    }

    /**
     * @param string $methodName
     * @param string $annotationName
     * @return object|array|null
     */
    private function resolveMethodAnnotation(string $methodName, string $annotationName)
    {
        $this->resolveMethodAnnotations($methodName);
        $this->resolveUnqualified($this->resolvedMethodAnnotations[$this->class][$methodName], $annotationName);
        return $this->resolvedMethodAnnotations[$this->class][$methodName][$annotationName] ?? null;
    }

    /**
     * If you use unqualified annotations, it will slow things down, but can still be supported.
     * @param array $resolvedAnnotations
     * @param string $annotationName
     */
    private function resolveUnqualified(array &$resolvedAnnotations, string $annotationName): void
    {
        if (!isset($resolvedAnnotations[$annotationName])) {
            $shortClassName = $this->getShortClassName($annotationName);
            $generic = $resolvedAnnotations[$shortClassName] ?? null;
            if ($generic && $generic instanceof AnnotationGeneric) {
                $resolvedAnnotations[$annotationName] = $this->resolver->convertGenericToClass($generic, $annotationName);
            }
        }
    }

    /**
     * Add annotation to index, keyed on resolved class name if possible, otherwise generic annotation name.
     * @param array $index
     * @param string $name
     * @param $resolvedAnnotation
     */
    private function addResolvedToIndex(array &$index, string $name, $resolvedAnnotation): void
    {
        $resolvedAnnotations = is_array($resolvedAnnotation) ? $resolvedAnnotation : [$resolvedAnnotation];
        foreach ($resolvedAnnotations as $annotation) {
            if (!($annotation instanceof AnnotationGeneric)) {
                $resolvedClassName = get_class($annotation);
                if ($resolvedClassName && $resolvedClassName != $name) {
                    $this->addToIndex($index, $resolvedClassName, $annotation);
                    return;
                }
            }
            $this->addToIndex($index, $name, $annotation);
        }
    }

    /**
     * If we already have an item in the index, turn it into an array and add to the array; otherwise, just set the
     * value directly (we only want to return an array if the same annotation appears more than once on an item).
     * @param array $index
     * @param string $name
     * @param object $value
     */
    private function addToIndex(array &$index, string $name, object $value): void
    {
        if (isset($index[$name])) {
            if (!is_array($index[$name])) {
                $index[$name] = [$index[$name]];
            }
            $index[$name][] = $value;
        } else {
            $index[$name] = $value;
        }
    }

    /**
     * Quickly parse a full class name to get the last part (short class name). No need for reflection here.
     * @param string $fullClassName
     * @return string Just the class part
     */
    private function getShortClassName(string $fullClassName): string
    {
        return substr(strrchr($fullClassName, '\\'), 1) ?? '';
    }

    /**
     * Throw up if there is a typo in the host class name.
     * @param $className
     * @throws AnnotationReaderException
     */
    private function assertClassExists(string $className): void
    {
        if (!class_exists($className)) {
            throw new AnnotationReaderException(sprintf('Class %1$s does not exist.', $className));
        }
    }

    /**
     * Decide whether to throw or return null.
     * @param \Exception $ex
     * @param bool $returnEmptyArray
     * @return array|null
     * @throws \Exception
     */
    private function handleException(\Exception $ex, bool $returnEmptyArray = false): ?array
    {
        $this->lastErrorMessage = $ex->getMessage();
        if ($this->throwExceptions) {
            throw $ex;
        }

        return $returnEmptyArray ? [] : null;
    }
}
