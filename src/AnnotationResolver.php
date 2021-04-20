<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

use Objectiphy\Objectiphy\Mapping\ObjectiphyAnnotation;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Resolve annotation string values into objects
 */
class AnnotationResolver
{
    public string $lastErrorMessage = '';
    public bool $objectiphyAnnotationError = false;
    
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
        $this->classNameAttributes = array_map('strtolower', $classNameAttributes);
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
        return $this->resolveAnnotation('c', $name, $value, []);
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
        string $value,
        array $children = []
    ): object {
        $reflectionProperty = $reflectionClass->hasProperty($propertyName) 
            ? $reflectionClass->getProperty($propertyName) 
            : null;
        $this->initialise($reflectionClass, $reflectionProperty);

        return $this->resolveAnnotation('p:' . $propertyName, $name, $value, $children);
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
        string $value,
        array $children = []
    ): object {
        $reflectionMethod = $reflectionClass->hasMethod($methodName) 
            ? $reflectionClass->getMethod($methodName) 
            : null;
        $this->initialise($reflectionClass, null, $reflectionMethod);

        return $this->resolveAnnotation('m:' . $methodName, $name, $value, $children);
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
            $generic->value,
            []
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
     * appear once per item.
     */
    private function resolveAnnotation(string $itemName, string $name, string $value, array $children): object
    {
        $class = $this->reflectionClass->getName();
        $annotationClass = $this->aliasFinder->findClassForAlias($this->reflectionClass, $name, false);
        try {
            $resolved = $this->convertValueToObject($class, $itemName, $annotationClass, $value, $children);
        } catch (\Exception $ex) {
            $args = [$annotationClass, $class, $ex->getMessage()];
            if (strpos($itemName, ':') !== false) {
                $type = strtok($itemName, ':') == 'm' ? 'method' : 'property';
                $item = strtok(':');
                $args = array_merge($args, [$type, $item]);
                $this->lastErrorMessage = sprintf('Error parsing annotation \'%1$s\' on %4$s \'%5$s\' of \'%2$s\' - %3$s', ...$args);
            } else {
                $this->lastErrorMessage = sprintf('Error parsing annotation \'%1$s\' on \'%2$s\' - %3$s', ...$args);
            }
            $this->objectiphyAnnotationError = $annotationClass == ObjectiphyAnnotation::class;
        } finally {
            return $resolved ?? $this->populateGenericAnnotation($name, $value);
        }
    }

    /**
     * Convert annotation string value into an object.
     * @param string $className
     * @param string $itemName
     * @param string $annotationClass
     * @param string $value
     * @return object
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    private function convertValueToObject(
        string $className,
        string $itemName,
        string $annotationClass,
        string $value,
        array $children
    ): ?object {
        if (class_exists($annotationClass)) {
            //Extract attribute values
            $this->attributes[$className][$itemName][$annotationClass] = $this->extractPropertyValues($value);
            $attributes =& $this->attributes[$className][$itemName][$annotationClass];

            //Instantiate
            $annotationReflectionClass = new \ReflectionClass($annotationClass);
            $constructor = $annotationReflectionClass->getConstructor();
            if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                $mandatoryArgs = $this->getMandatoryConstructorArgs(
                    $annotationClass,
                    $annotationReflectionClass,
                    $attributes
                );
                try {
                    $object = new $annotationClass(...$mandatoryArgs);
                } catch (\Throwable $ex) {
                    //Symfony serialization groups now insist on a 'value' key for each entry
                    if (strpos($annotationClass, 'Group') !== false
                        && is_array($mandatoryArgs[0] ?? null)
                        && array_key_first($mandatoryArgs[0]) == 0
                    ) {
                        $mandatoryArgs[0] = ['value' => $mandatoryArgs[0][0]];
                        $object = new $annotationClass(...$mandatoryArgs);
                    }
                }
            } else {
                $object = new $annotationClass();
            }

            //Set properties
            foreach ($attributes as $attributeName => $attributeValue) {
                if (is_string($attributeValue) && array_key_exists($attributeValue, $children)) {
                    $attributeValue = $children[$attributeValue];
                }
                $this->setPropertyOnObject($object, $attributeName, $attributeValue, $attributes);
            }

            return $object;
        }
    }

    /**
     * Create and populate the AnnotationGeneric object to represent the annotation.
     * @param string $annotationName
     * @param string $annotationValue
     * @return AnnotationGeneric
     */
    private function populateGenericAnnotation(
        string $annotationName,
        string $annotationValue
    ): AnnotationGeneric {
        //Create a closure to resolve type aliases
        $aliasFinder = function($alias) {
            return $this->aliasFinder->findClassForAlias($this->reflectionClass, $alias, false);
        };
        return new AnnotationGeneric(
            $annotationName,
            $annotationValue,
            $aliasFinder,
            $this->reflectionClass,
            $this->reflectionProperty,
            $this->reflectionMethod
        );
    }

    /**
     * When creating an object to represent an annotation, see if there are any mandatory constructor arguments (which
     * can then be populated based on values in the annotation).
     * @param string $annotation
     * @param \ReflectionClass $annotationReflectionClass
     * @param array $attributes
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
                $argName = $constructorArg->getName();
                $attributeKey = array_key_exists($argName, $attributes) ? $argName : ''; //Default constructor arg could be keyed on empty string
                if (!$attributeKey) {
                    //Find the key with a case insensitive search and make a copy with the correct name
                    foreach (array_keys($attributes) as $key) {
                        if (strtolower($key) == strtolower($argName)) {
                            $attributes[$argName] = $attributes[$key];
                            $attributeKey = $argName;
                            break;
                        }
                    }
                }

                if (!array_key_exists($attributeKey, $attributes)) {
                    //We cannot create it!
                    $errorMessage = sprintf(
                        'Cannot create instance of annotation %1$s (defined on %2$s) because constructor argument %3$s is mandatory and has not been supplied (or the annotation is malformed so could not be parsed).',
                        $annotation,
                        $this->reflectionClass->getName(),
                        $constructorArg->getName()
                    );
                    throw new AnnotationReaderException($errorMessage);
                }
                $mandatoryArgs[] = $attributes[$attributeKey];
            }
        }

        return $mandatoryArgs;
    }

    /**
     * Takes the content of an annotation value and converts to an associative array.
     * @param string $value Annotation value, eg: (attr1="value1", attr2={"value2", 3}).
     * @return array eg. ['attr1' => 'value1', 'attr2' => ['value2', 3]].
     * @throws AnnotationReaderException
     */
    private function extractPropertyValues(string $value): array
    {
        //Ugly, but we need to wrap all attribute names in quotes to json_decode into an array
        $attrPositions = [];
        $attrStart = 0;
        $attrEnd = 0;
        $previousNonSpace = '';
        $nextNonSpace = '';
        $openCurly = null;
        foreach (str_split($value) as $i => $char) {
            switch ($char) {
                case '(':
                case ',':
                    for ($j = $i; $j <= strlen($value); $j++) {
                        $j++;
                        $nextChar = substr($value, $j, 1);
                        if (!ctype_space($nextChar)) {
                            $nextNonSpace = $nextChar;
                            break;
                        }
                    }
                    if ($nextNonSpace != '"') { //Already wrapped in quotes, don't do it again
                        $attrStart = $i + 1; //We will trim any whitespace later
                    }
                    break;
                case '=':
                    if ($attrStart) {
                        if ($previousNonSpace != '"') { //Already wrapped in quotes, don't do it again
                            $attrEnd = $i - 1;
                            $attrPositions[] = $attrStart;
                            $attrPositions[] = $attrEnd + 1;
                            $attrStart = 0;
                            $attrEnd = 0;
                        }
                    }
                    $openCurly = null;
                    break;
                case '{':
                    $openCurly = $i;
                    break;
                case '}':
                    if ($openCurly !== null) {
                        //Replace last openCurly and this one with square brackets
                        $value = substr($value, 0, $openCurly)
                            . '[' . substr($value, $openCurly + 1, ($i - $openCurly) - 1) . ']'
                            . substr($value, $i + 1);
                    }
                    $openCurly = null;
                    break;
            }
        }

        //Wrap all the keys in quotes
        foreach ($attrPositions as $index => $position) {
            if (substr($value, $position + $index, 1) != '"') {
                $value = substr($value, 0, $position + $index) . '"' . substr($value, $position + $index);
            }
        }

        if ($value) {
            //Try to make it valid JSON
            $jsonString = str_replace(
                ['(', ')', '=', '\\', "\t", "\r", "\n"],
                ['{', '}', ':', '\\\\', '', '', ''],
                $value
            );
            if (strpos($jsonString, '{[') !== false) {
                $jsonString = str_replace(
                    '{[',
                    '{"":[',
                    $jsonString
                );
            }
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
            } elseif (json_last_error() != \JSON_ERROR_NONE) {
                throw new AnnotationReaderException('Could not resolve annotation: ' . json_last_error_msg());
            }
        }

        return $cleanArray ?? [];
    }

    /**
     * Populate the properties of an object that represents an annotation.
     * @param object $object
     * @param string $property
     * @param $propertyValue
     * @param array $attributes
     */
    private function setPropertyOnObject(object $object, string $property, $propertyValue, array &$attributes): void
    {
        try {
            if (property_exists($object, $property)) {
                if (in_array(strtolower($property), $this->classNameAttributes)) {
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
