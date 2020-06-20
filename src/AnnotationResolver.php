<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Resolve annotation string values into objects
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class AnnotationResolver
{
    private ClassAliasFinder $aliasFinder;
    private array $classNameAttributes;
    private array $attributes;
    private \ReflectionClass $reflectionClass;
    private ?\ReflectionProperty $reflectionProperty = null;
    private ?\ReflectionMethod $reflectionMethod = null;
    
    private array $resolvedClassAnnotations = [];
    private array $resolvedPropertyAnnotations = [];
    private array $resolvedMethodAnnotations = [];

    public function __construct(ClassAliasFinder $aliasFinder = null, array $classNameAttributes = [])
    {
        $this->aliasFinder = $aliasFinder ?? new ClassAliasFinder();
        $this->setClassNameAttributes($classNameAttributes);
    }

    /**
     * @param array $classNameAttributes Attributes whose value should be resolved to a class name, if it exists.
     * Generally this should be set once at the start. Unit tests may call it multiple times.
     */
    public function setClassNameAttributes(array $classNameAttributes)
    {
        $this->classNameAttributes = $classNameAttributes;
        $this->resolvedClassAnnotations = [];
        $this->resolvedPropertyAnnotations = [];
        $this->resolvedMethodAnnotations = [];
    }

    /**
     * Returns an associative array of the properties that were specified for a custom annotation. We need this because
     * it is impossible to tell otherwise whether a value was present in the annotation, or whether it is just the
     * default value for the object or was set separately.
     * @param string $key Host class name, type (#c# = class, #p:<propertyName># = property, #m:<methodName># = method), 
     * and annotation name, eg. "MyNamespace\MyEntity#p:MyProperty#OtherNamespace\AnnotationName"
     * @return array
     */
    public function getAttributesRead(string $key): array
    {
        return $this->attributes[$key] ?? [];
    }

    public function resolveClassAnnotation(\ReflectionClass $reflectionClass, string $name, string $value)
    {
        $this->initialise($reflectionClass);
        return $this->resolveAnnotation($this->resolvedClassAnnotations, 'c', '', $name, $value);
    }

    public function resolvePropertyAnnotation(\ReflectionClass $reflectionClass, string $propertyName, string $name, string $value)
    {
        $this->initialise($reflectionClass, $reflectionClass->hasProperty($propertyName) ? $reflectionClass->getProperty($propertyName) : null);
        return $this->resolveAnnotation($this->resolvedPropertyAnnotations, 'p', $propertyName, $name, $value);
    }

    public function resolveMethodAnnotation(\ReflectionClass $reflectionClass, string $methodName, string $name, string $value)
    {
        $this->initialise($reflectionClass, null, $reflectionClass->hasMethod($methodName) ? $reflectionClass->getMethod($methodName) : null);
        return $this->resolveAnnotation($this->resolvedMethodAnnotations, 'm', $methodName, $name, $value);
    }
    
    public function convertGenericToClass(AnnotationGeneric $generic, $className)
    {
        return $this->convertValueToObject(
            $className,
            $generic->value,
            $generic->getKeyPrefix() . $className
        ) ?? $generic;
    }

    protected function initialise(\ReflectionClass $class, ?\ReflectionProperty $property = null, ?\ReflectionMethod $method = null)
    {
       $this->reflectionClass = $class;
       $this->reflectionProperty = $property;
       $this->reflectionMethod = $method;
    }
    private function resolveAnnotation(array &$resolvedAnnotations, $itemType, $itemName, $name, $value)
    {
        $class = $this->reflectionClass->getName();
        $annotationClass = $this->aliasFinder->findClassForAlias($this->reflectionClass, $name, false);
        $cacheKey = $class . ($itemName ? '#' . $itemName : '');
        $cachedValue = $resolvedAnnotations[$cacheKey][$annotationClass] ?? null;
        if (!$cachedValue || $cachedValue instanceof AnnotationGeneric) { //If generic, there could be multiple - cannot cache by name
            $resolvedAnnotations[$cacheKey] ??= [];
            $key = $class . '#' . $itemType . ':' . $itemName . '#' . $annotationClass;
            try {
                $resolved = $this->convertValueToObject($annotationClass, $value, $key);
            } catch (\Exception $ex) {}
            $resolved = $resolved ?? $this->populateGenericAnnotation($name, $value);
            $resolvedAnnotations[$cacheKey][$annotationClass] = $resolved;
        } else {
            $resolved = $cachedValue;
        }

        return $resolved;
    }

    /**
     * Convert annotation string value into an object.
     * @return mixed
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    private function convertValueToObject(
        string $annotation,
        string $value,
        string $key
    ): ?object {
        $this->attributes[$key] = $this->extractPropertyValues($value);
        $annotationReflectionClass = new \ReflectionClass($annotation);
        $constructor = $annotationReflectionClass->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            $mandatoryArgs = $this->getMandatoryConstructorArgs($annotation, $annotationReflectionClass, $key);
            $object = new $annotation(...$mandatoryArgs);
        } else {
            $object = new $annotation();
        }

        foreach ($this->attributes[$key] as $attributeName => $attributeValue) {
            $this->setPropertyOnObject($object, $attributeName, $attributeValue, $key);
        }

        return $object;
    }

    /**
     * Create and populate the AnnotationGeneric object to represent the annotation.
     * @param string $annotationName
     * @param string $annotationValue
     * @param string $commentKey
     */
    private function populateGenericAnnotation(
        string $annotationName,
        string $annotationValue
    ): AnnotationGeneric {
        //Create a closure to resolve type aliases
        $aliasFinder = function($alias) {
            return $this->aliasFinder->findClassForAlias($this->reflectionClass, $alias, false);
        };
        $generic = new AnnotationGeneric($annotationName, $annotationValue, $aliasFinder, $this->reflectionClass, $this->reflectionProperty, $this->reflectionMethod);

        return $generic;
    }

    /**
     * When creating an object to represent an annotation, see if there are any mandatory constructor arguments (which
     * can then be populated based on values in the annotation).
     * @param string $annotation
     * @param \ReflectionClass $annotationReflectionClass
     * @param array $properties
     * @return array
     * @throws AnnotationReaderException
     */
    private function getMandatoryConstructorArgs(
        string $annotation,
        \ReflectionClass $annotationReflectionClass, 
        string $key
    ): array {
        $mandatoryArgs = [];
        foreach ($annotationReflectionClass->getConstructor()->getParameters() as $constructorArg) {
            if (!$constructorArg->isOptional()) {
                if (!array_key_exists($constructorArg->getName(), $this->attributes[$key])) {
                    //We cannot create it!
                    $errorMessage = sprintf(
                        'Cannot create instance of annotation %1$s (defined on %2$s) because constructor argument %3$s is mandatory and has not been supplied (or the annotation is malformed so could not be parsed).',
                        $annotation,
                        $this->reflectionClass->getName(),
                        $constructorArg->getName()
                    );
                    throw new AnnotationReaderException($errorMessage);
                }
                $mandatoryArgs[] = $this->attributes[$key][$constructorArg->getName()];
            }
        }

        return $mandatoryArgs;
    }

    /**
     * Takes the content of an annotation value and converts to an associative array.
     * @param string $value Annotation value, eg: (attr1="value1", attr2={"value2", 3}).
     * @return array eg. ['attr1' => 'value1', 'attr2' => ['value2', 3]].
     */
    private function extractPropertyValues(string $value): array
    {
        //Ugly, but we need to wrap all attribute names in quotes to json_decode into an array
        $attrPositions = [];
        $attrStart = 0;
        $attrEnd = 0;
        foreach (str_split($value) as $i => $char) {
            switch ($char) {
                case '(';
                case ',';
                    $attrStart = $i + 1; //We will trim any whitespace later
                    break;
                case '=':
                    if ($attrStart) {
                        $attrEnd = $i - 1;
                        $attrPositions[] = $attrStart;
                        $attrPositions[] = $attrEnd + 1;
                        $attrStart = 0;
                        $attrEnd = 0;
                    }
                    break;
            }
        }

        //Wrap all the keys in quotes
        foreach ($attrPositions as $index => $position) {
            $value = substr($value, 0, $position + $index) . '"' . substr($value, $position + $index);
        }

        //Try to make it valid JSON
        $jsonString = str_replace(
            ['{', '}', '(', ')', '=', '\\', "\t", "\r", "\n"],
            ['[', ']', '{', '}', ':', '\\\\', '', '', ''],
            $value
        );
        $array = json_decode($jsonString, true, 512, \JSON_INVALID_UTF8_IGNORE | \JSON_BIGINT_AS_STRING);
        if ($array) {
            //Now trim any whitespace from keys (values should be ok)
            $trimmedKeys = array_map('trim', array_keys($array));
            $cleanArray = array_combine($trimmedKeys, array_values($array));
        }

        return $cleanArray ?? [];
    }

    /**
     * Populate the properties of an object that represents an annotation.
     */
    private function setPropertyOnObject(object $object, string $property, $propertyValue, string $key): void
    {
        try {
            if (property_exists($object, $property)) {
                if (in_array($property, $this->classNameAttributes)) {
                    $propertyValue = $this->aliasFinder->findClassForAlias($this->reflectionClass, $propertyValue) ?: $propertyValue;
                }
                $reflectionProperty = new \ReflectionProperty($object, $property);
                if ($reflectionProperty->isPublic()) {
                    $object->{$property} = $propertyValue;
                } else
                    $setter = 'set' . ucfirst($property);
                    if (method_exists($object, $setter)) {
                    $reflectionMethod = new \ReflectionMethod($object, $setter);
                    if ($reflectionMethod->isPublic()) {
                        $object->{$setter}($propertyValue);
                    }
                }
                $this->attributes[$key][$property] = $propertyValue;
            }
        } catch (\Exception $ex) {}
    }
}
