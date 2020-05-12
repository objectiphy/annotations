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

    private \ReflectionClass $reflectionClass;
    private DocParser $docParser;
    private bool $throwExceptions;
    private ClassAliasFinder $aliasFinder;

    /**
     * @param DocParser $docParser
     * @param ClassAliasFinder $aliasFinder
     * @param array $classNameAttributes If any attributes of the annotation need to be resolved to fully qualified
     * class names, specify the attribute names here.
     * @param bool $throwExceptions For silent operation, set to false, and any errors will be ignored, and annotations
     * that could not be parsed will be returned as null. Either way, the lastErrorMessage property will be populated
     * with any exception messages.
     */
    public function __construct(
        DocParser $docParser = null,
        ClassAliasFinder $aliasFinder = null,
        array $classNameAttributes = [],
        $throwExceptions = true
    ) {
        $this->docParser = $docParser ?? new DocParser();
        $this->aliasFinder = $aliasFinder ?? new ClassAliasFinder();
        $this->setThrowExceptions($throwExceptions);
        $this->setClassNameAttributes($classNameAttributes);
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
     * Identify which attributes on the annotations we are reading refer to class names that might need to be expanded
     * (ie. where the class name specified in the annotation is relative to a use statement in the same file).
     * @param array $classNameAttributes
     */
    public function setClassNameAttributes(array $classNameAttributes): void
    {
        $this->docParser->setClassNameAttributes($classNameAttributes);
    }

    /**
     * @param string $className Name of class that has (or might have) the annotation.
     * @param string $annotationName Name of the annotation.
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromClass(string $className, string $annotationName): ?object
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            $docComment = $this->reflectionClass->getDocComment();

            return $this->parseDocComment($docComment, 'c#' . $className, $annotationName);
        } catch (\Exception $ex) {
            return $this->handleException($ex);
        }
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
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            if ($this->reflectionClass->hasProperty($propertyName)) {
                $reflectionProperty = $this->reflectionClass->getProperty($propertyName);
                $docComment = $reflectionProperty->getDocComment();

                return $this->parseDocComment($docComment, 'p#' . $reflectionProperty->getName(), $annotationName);
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
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationFromMethod(string $className, string $methodName, string $annotationName): ?object
    {
        $this->lastErrorMessage = '';
        try {
            $this->assertClassExists($className);
            $this->reflectionClass = new \ReflectionClass($className);
            if ($this->reflectionClass->hasMethod($methodName)) {
                $reflectionMethod = $this->reflectionClass->getMethod($methodName);
                $docComment = $reflectionMethod->getDocComment();

                return $this->parseDocComment($docComment, 'm#' . $reflectionMethod->getName(), $annotationName);
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
     * (just seemed like a cool thing to support, probably useless in real life though!) - these methods could 
     * be removed and the class would still work, however, it is also handy to be able to pass in a reflection object 
     * to obtain annotation information if you already have one available - slightly more efficient than relying on 
     * this class to create the reflection objects for you.
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
            $docComment = $this->reflectionClass->getDocComment();
            return $this->parseDocComment($docComment, 'c#' . $class->getName(), $annotationName);
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
            $docComment = $property->getDocComment();
            return $this->parseDocComment($docComment, 'p#' . $property->getName(), $annotationName);
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
            $docComment = $method->getDocComment();
            return $this->parseDocComment($docComment, 'm#' . $method->getName(), $annotationName);
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
            $docComment = $class->getDocComment();

            return $this->unifiedArrayValues($this->parseDocComment($docComment, 'c#' . $class->getName()));
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
            $docComment = $property->getDocComment();
            return $this->unifiedArrayValues($this->parseDocComment($docComment, 'p#' . $property->getName()));
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
            $docComment = $method->getDocComment();
            return $this->unifiedArrayValues($this->parseDocComment($docComment, 'm#' . $method->getName()));
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
     * Defer to the parser after validation.
     * @param string $docComment
     * @param string $annotationName
     * @return object|array|null
     * @throws AnnotationReaderException
     */
    private function parseDocComment(string $docComment, string $commentKey, string $annotationName = '**ALL**')
    {
        if ($annotationName != '**ALL**'  && !class_exists($annotationName)) {
            $annotationName = $this->aliasFinder->findClassForAlias($this->reflectionClass, $annotationName, false);
        }
        
        if ($annotationName == '**ALL**') {
            return $this->docParser->getAllAnnotations($this->reflectionClass, $docComment, $commentKey);
        } else {
            return $this->docParser->getAnnotation($this->reflectionClass, $docComment, $commentKey, $annotationName);
        }
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
