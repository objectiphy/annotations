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
    private DocParser $docParser;
    private AnnotationResolver $resolver;

    /**
     * @param DocParser $docParser
     * @param AnnotationResolver $resolver
     * @param array $classNameAttributes If any attributes of the annotation need to be resolved to fully qualified
     * class names, specify the attribute names here.
     * @param bool $throwExceptions For silent operation, set to false, and any errors will be ignored, and annotations
     * that could not be parsed will be returned as null. Either way, the lastErrorMessage property will be populated
     * with any exception messages.
     */
    public function __construct(
        DocParser $docParser = null,
        AnnotationResolver $resolver = null,
        array $classNameAttributes = [],
        $throwExceptions = true
    ) {
        $this->setThrowExceptions($throwExceptions);
        $this->docParser = $docParser ?? new DocParser();
        $this->resolver = $resolver ?? new AnnotationResolver();
        $this->setClassNameAttributes($classNameAttributes);
    }

    /**
     * Just pass it along
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void
    {
        $this->resolver->setClassNameAttributes($classNameAttributes);
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
     * Returns an associative array of the properties that were specified for a custom annotation. We need this because 
     * it is impossible to tell otherwise whether a value was present in the annotation, or whether it is just the 
     * default value for the object or was set separately.
     * @param string $className
     * @return array
     */
    public function getAttributesRead(string $key): array
    {
        return $this->resolver->getAttributesRead($key);
    }

    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @return array | object | null
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            return $this->getClassAnnotation($this->reflectionClass, $annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
    }

    /**
     * @param string $className Name of class that has the property whose annotation we want.
     * @param string $propertyName Name of property that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromProperty(string $className, string $propertyName, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            while ($this->reflectionClass && !$this->reflectionClass->hasProperty($propertyName)) {
                $this->reflectionClass = $this->reflectionClass->getParentClass() ?: null;
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
     * @return object | array | null If the annotation appears more than once, an array will be returned
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName)
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            while ($this->reflectionClass && !$this->reflectionClass->hasMethod($methodName)) {
                $this->reflectionClass = $this->reflectionClass->getParentClass() ?: null;
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
            $this->reflectionClass = $class;
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
            $this->reflectionClass = $property->getDeclaringClass();
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
            $this->reflectionClass = $method->getDeclaringClass();
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
            $this->reflectionClass = $class;
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
            $this->reflectionClass = $property->getDeclaringClass();
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
            $this->reflectionClass = $method->getDeclaringClass();
            return $this->unifiedArrayValues($this->resolveMethodAnnotations($method->getName()));
        } catch (\Exception $ex) {
            return $this->handleException($ex, true);
        }
    }

//    /**
//     * Given an associative array, which might contain indexed arrays, combine into one indexed array. For example:
//     * [
//     *   'param' => [
//     *     0 => 'value1',
//     *     1 => 'value2'
//     *   ],
//     *   'var' => 'value3',
//     *   'something_else' => [
//     *     0 => 'value4'
//     *   ]
//     * ]
//     *
//     * ...would return:
//     *
//     * [
//     *   0 => 'value1',
//     *   1 => 'value2',
//     *   2 => 'value3',
//     *   3 => 'value4'
//     * ]
//     * @param array $array
//     */
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
//    private function unifiedArrayValues(array $array): array
//    {
//        return array_values($array);
//    }

    /******************************************************************************************************************
     * End of Doctrine compatibility methods.
     *****************************************************************************************************************/

    /**
     * Defer to the parser and resolver
     */
    private function resolveClassAnnotations(): array
    {
        $resolvedAnnotations = [];
        $annotations = $this->docParser->getClassAnnotations($this->reflectionClass);
        foreach ($annotations ?? [] as $index => $nameValuePair) {
            foreach ($nameValuePair as $name => $value) {
                $resolved = $this->resolver->resolveClassAnnotation($this->reflectionClass, $name, $value);
                $this->addResolvedToIndex($resolvedAnnotations, $name, $resolved);
            }
        }
        
        return $resolvedAnnotations;
    }

    private function resolvePropertyAnnotations(string $propertyName): array
    {
        $resolvedAnnotations = [];
        $annotations = $this->docParser->getPropertyAnnotations($this->reflectionClass);
        if (!empty($annotations[$propertyName])) {
            foreach ($annotations[$propertyName] as $nameValuePair) {
                foreach ($nameValuePair as $name => $value) {
                    $resolved = $this->resolver->resolvePropertyAnnotation($this->reflectionClass, $propertyName, $name, $value);
                    $this->addResolvedToIndex($resolvedAnnotations, $name, $resolved);
                }
            }
        }
        
        return $resolvedAnnotations;
    }

    private function resolveMethodAnnotations(string $methodName): array
    {
        $resolvedAnnotations = [];
        $annotations = $this->docParser->getMethodAnnotations($this->reflectionClass);
        if (!empty($annotations[$methodName])) {
            foreach ($annotations[$methodName] as $nameValuePair) {
                foreach ($nameValuePair as $name => $value) {
                    $resolved = $this->resolver->resolveMethodAnnotation($this->reflectionClass, $methodName, $name, $value);
                    $this->addResolvedToIndex($resolvedAnnotations, $name, $resolved);
                }
            }
        }

        return $resolvedAnnotations;
    }

    private function resolveClassAnnotation(string $annotationName)
    {
        $resolvedAnnotations = $this->resolveClassAnnotations();
        return $resolvedAnnotations[$annotationName] ?? null;
    }

    private function resolvePropertyAnnotation(string $propertyName, string $annotationName)
    {
        $resolvedAnnotations = $this->resolvePropertyAnnotations($propertyName);
        $this->resolveUnqualified($resolvedAnnotations, $annotationName);
        return $resolvedAnnotations[$annotationName] ?? null;
    }

    /**
     * If you use unqualified annotations, it will slow things down, but can still be supported.
     * @param array $resolvedAnnotations
     * @param string $annotationName
     */
    private function resolveUnqualified(array &$resolvedAnnotations, string $annotationName)
    {
        if (!isset($resolvedAnnotations[$annotationName])) {
            $shortClassName = $this->getShortClassName($annotationName);
            $generic = $resolvedAnnotations[$shortClassName] ?? null;
            if ($generic && $generic instanceof AnnotationGeneric) {
                $resolvedAnnotations[$annotationName] = $this->resolver->convertGenericToClass($generic, $annotationName);
            }
        }
    }

    private function resolveMethodAnnotation(string $methodName, string $annotationName)
    {
        $resolvedAnnotations = $this->resolveMethodAnnotations($methodName);
        return $resolvedAnnotations[$annotationName] ?? null;
    }

    /**
     * Add alias, full class name, and unqualified class name to the index
     * @param array $index
     * @param string $name
     * @param $resolvedAnnotation
     */
    private function addResolvedToIndex(array &$index, string $name, $resolvedAnnotation)
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

    private function addToIndex(array &$index, $name, $value)
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

    private function getShortClassName(string $fullClassName)
    {
        return substr(strrchr($fullClassName, '\\'), 1);
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
     * @return array|null
     * @throws \Exception
     */
    private function handleException(\Exception $ex, bool $returnEmptyArray = false)
    {
        $this->lastErrorMessage = $ex->getMessage();
        if ($this->throwExceptions) {
            throw $ex;
        }

        return $returnEmptyArray ? [] : null;
    }
}
