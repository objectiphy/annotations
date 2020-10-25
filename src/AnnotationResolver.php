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

    /**
     * AnnotationResolver constructor.
     * @param ClassAliasFinder|null $aliasFinder
     * @param array $classNameAttributes
     */
    public function __construct(array $classNameAttributes = [], ClassAliasFinder $aliasFinder = null)
    {
        $this->aliasFinder = $aliasFinder ?? new ClassAliasFinder();
        $this->setClassNameAttributes($classNameAttributes);
    }

    /**
     * @param array $classNameAttributes Attributes whose value should be resolved to a class name, if it exists.
     * Generally this should be set once at the start. Unit tests may call it multiple times.
     */
    public function setClassNameAttributes(array $classNameAttributes): void
    {
        $this->classNameAttributes = $classNameAttributes;
    }

    /**
     * Returns an associative array of the properties that were specified for a custom annotation. We need this because
     * it is impossible to tell otherwise whether a value was present in the annotation, or whether it is just the
     * default value for the object or was set separately.
     * @param string $hostClassName Host class name.
     * @param string $itemName type and property or method name ('p:' followed by property name, 'm:' followed by method
     * name, or 'c' for a class annotation).
     * @param string $annotationClassName Fully qualified annotation class name.
     * @return array
     */
    public function getAttributesRead(string $hostClassName, string $itemName, string $annotationClassName): array
    {
        return $this->attributes[$hostClassName][$itemName][$annotationClassName] ?? [];
    }

    /**
     * @param \ReflectionClass $reflectionClass Host class.
     * @param string $name Name of annotation.
     * @param string $value String value of annotation.
     * @return object|AnnotationGeneric
     */
    public function resolveClassAnnotation(\ReflectionClass $reflectionClass, string $name, string $value): object
    {
        $this->initialise($reflectionClass);
        return $this->resolveAnnotation('c', $name, $value);
    }

    /**
     * @param \ReflectionClass $reflectionClass Host class.
     * @param string $propertyName Name of property on host class.
     * @param string $name Name of annotation.
     * @param string $value String value of annotation.
     * @return object|AnnotationGeneric
     * @throws \ReflectionException
     */
    public function resolvePropertyAnnotation(
        \ReflectionClass $reflectionClass, 
        string $propertyName, 
        string $name, 
        string $value
    ): object {
        $reflectionProperty = $reflectionClass->hasProperty($propertyName) 
            ? $reflectionClass->getProperty($propertyName) 
            : null;
        $this->initialise($reflectionClass, $reflectionProperty);

        return $this->resolveAnnotation('p:' . $propertyName, $name, $value);
    }

    /**
     * @param \ReflectionClass $reflectionClass Host class.
     * @param string $methodName Name of method on host class.
     * @param string $name Name of annotation.
     * @param string $value String value of annotation.
     * @return object|AnnotationGeneric
     * @throws \ReflectionException
     */
    public function resolveMethodAnnotation(
        \ReflectionClass $reflectionClass, 
        string $methodName, 
        string $name, 
        string $value
    ): object {
        $reflectionMethod = $reflectionClass->hasMethod($methodName) 
            ? $reflectionClass->getMethod($methodName) 
            : null;
        $this->initialise($reflectionClass, null, $reflectionMethod);

        return $this->resolveAnnotation('m:' . $methodName, $name, $value);
    }

    /**
     * Take a previously resolved generic annotation and convert it to a custom annotation (for cases where we did not
     * know the full class name at time of initial resolution).
     * @param AnnotationGeneric $generic
     * @param string $className
     * @return object
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    public function convertGenericToClass(AnnotationGeneric $generic, string $className): object
    {
        return $this->convertValueToObject(
            $generic->parentClass->getName(),
            $generic->getItemName(),
            $className,
            $generic->value
        );
    }

    /**
     * Just store things in local properties for easy access.
     * @param \ReflectionClass $class
     * @param \ReflectionProperty|null $property
     * @param \ReflectionMethod|null $method
     */
    protected function initialise(
        \ReflectionClass $class, 
        ?\ReflectionProperty $property = null, 
        ?\ReflectionMethod $method = null
    ): void {
       $this->reflectionClass = $class;
       $this->reflectionProperty = $property;
       $this->reflectionMethod = $method;
    }

    /**
     * Do the bidness.
     * @param string $itemName 'p:' followed by property name, 'm:' followed by method name, or 'c' for a class
     * annotation.
     * @param string $name Annotation name.
     * @param $value String value of annotation.
     * @return object|AnnotationGeneric Either an instance of a custom annotation class, or AnnotationGeneric if no
     * custom class recognised. Custom class annotations can be cached by class name, as they would typically only
     * appear once per item. //TODO: Maybe we should not cache at all here - they are cached further up the chain, and
     * by not caching, we would allow for multiple instances of the same custom annotation on an item.
     */
    private function resolveAnnotation(string $itemName, string $name, string $value): object
    {
        $class = $this->reflectionClass->getName();
        $annotationClass = $this->aliasFinder->findClassForAlias($this->reflectionClass, $name, false);
        try {
            $resolved = $this->convertValueToObject($class, $itemName, $annotationClass, $value);
        } catch (\Exception $ex) {}

        return $resolved ?? $this->populateGenericAnnotation($name, $value);
    }

    /**
     * Convert annotation string value into an object.
     * @return object
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    private function convertValueToObject(
        string $className,
        string $itemName,
        string $annotationClass,
        string $value
    ): ?object {
        //Extract attribute values
        $this->attributes[$className][$itemName][$annotationClass] = $this->extractPropertyValues($value);
        $attributes =& $this->attributes[$className][$itemName][$annotationClass];

        //Instantiate
        $annotationReflectionClass = new \ReflectionClass($annotationClass);
        $constructor = $annotationReflectionClass->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            $mandatoryArgs = $this->getMandatoryConstructorArgs($annotationClass, $annotationReflectionClass, $attributes);
            $object = new $annotationClass(...$mandatoryArgs);
        } else {
            $object = new $annotationClass();
        }

        //Set properties
        foreach ($attributes as $attributeName => $attributeValue) {
            $this->setPropertyOnObject($object, $attributeName, $attributeValue, $attributes);
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
        $generic = new AnnotationGeneric(
            $annotationName,
            $annotationValue,
            $aliasFinder,
            $this->reflectionClass,
            $this->reflectionProperty,
            $this->reflectionMethod
        );

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
        array $attributes
    ): array {
        $mandatoryArgs = [];
        foreach ($annotationReflectionClass->getConstructor()->getParameters() as $constructorArg) {
            if (!$constructorArg->isOptional()) {
                if (!array_key_exists($constructorArg->getName(), $attributes)) {
                    //We cannot create it!
                    $errorMessage = sprintf(
                        'Cannot create instance of annotation %1$s (defined on %2$s) because constructor argument %3$s is mandatory and has not been supplied (or the annotation is malformed so could not be parsed).',
                        $annotation,
                        $this->reflectionClass->getName(),
                        $constructorArg->getName()
                    );
                    throw new AnnotationReaderException($errorMessage);
                }
                $mandatoryArgs[] = $attributes[$constructorArg->getName()];
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
                case '(':
                case ',':
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
//        $jsonString = str_replace(
//            ['{', '}', '(', ')', '=', '\\', "\t", "\r", "\n"],
//            ['[', ']', '{', '}', ':', '\\\\', '', '', ''],
//            $value
//        );
        $jsonString = str_replace(
            ['(', ')', '=', '\\', "\t", "\r", "\n"],
            ['{', '}', ':', '\\\\', '', '', ''],
            $value
        );
        $array = json_decode($jsonString, true, 512, \JSON_INVALID_UTF8_IGNORE | \JSON_BIGINT_AS_STRING);
        if ($array) {
            //Now trim any whitespace from keys (values should be ok)
            $trimmedKeys = array_map('trim', array_keys($array));
            $cleanArray = array_combine($trimmedKeys, array_values($array));
            foreach ($cleanArray as $cleanKey => $cleanValue) { //and the next level (not worth going any further though)
                if (is_array($cleanValue)) {
                    $trimmedCleanKeys = array_map('trim', array_keys($cleanValue));
                    $cleanArray[$cleanKey] = array_combine($trimmedCleanKeys, array_values($cleanValue));
                }
            }
        }

        return $cleanArray ?? [];
    }

    /**
     * Populate the properties of an object that represents an annotation.
     */
    private function setPropertyOnObject(object $object, string $property, $propertyValue, array &$attributes): void
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
                $attributes[$property] = $propertyValue;
            }
        } catch (\Exception $ex) {}
    }
}
