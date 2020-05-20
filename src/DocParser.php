<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Parses doc comments to resolve annotations.
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class DocParser
{
    private ClassAliasFinder $aliasFinder;
    private \ReflectionClass $hostReflectionClass;
    private array $classNameAttributes;
    private array $annotations = [];
    private array $properties = [];

    public function __construct(ClassAliasFinder $aliasFinder = null, array $classNameAttributes = [])
    {
        $this->aliasFinder = $aliasFinder ?? new ClassAliasFinder();
        $this->setClassNameAttributes($classNameAttributes);
    }

    /**
     * @param array $classNameAttributes Attributes whose value should be resolved to a class name, if it exists.
     */
    public function setClassNameAttributes(array $classNameAttributes)
    {
        $this->classNameAttributes = $classNameAttributes;
        $this->annotations = []; //Forget anything already cached as its attributes won't be resolved
    }

    /**
     * @param \ReflectionClass $hostReflectionClass The host class that contains the doc comment.
     * @param string $docComment The doc comment to parse.
     * @param string $commentKey Unique identifier for this comment within the class (eg. p#myProperty, m#myMethod).
     * @return array
     */
    public function getAllAnnotations(
        \ReflectionClass $hostReflectionClass, 
        string $docComment, 
        string $commentKey
    ): array {
        if (empty($this->annotations[$hostReflectionClass->getName()][$commentKey])) {
            $this->parseDocComment($hostReflectionClass, $docComment, $commentKey);
        }

        return $this->annotations[$hostReflectionClass->getName()][$commentKey] ?? [];
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param string $docComment The doc comment to parse.
     * @param string $commentKey Unique identifier for this comment within the class (eg. p#myProperty, m#myMethod).
     * @param string $annotationName Name of annotation to retrieve.
     * @return string|object|null
     */
    public function getAnnotation(
        \ReflectionClass $reflectionClass,
        string $docComment,
        string $commentKey,
        string $annotationName
    ) {
        if (empty($this->annotations[$reflectionClass->getName()][$commentKey])) {
            $this->parseDocComment($reflectionClass, $docComment, $commentKey);
        }

        $result = $this->annotations[$reflectionClass->getName()][$commentKey][$annotationName] ?? null;
        if (is_array($result) && count($result) == 1) {
            $result = reset($result);
        }
        return $result;
    }

    /**
     * Find the specified annotation in the doc comment and return its value as a string or object.
     * @param \ReflectionClass $hostReflectionClass Reflection of the class that contains the doc comment.
     * @param string $docComment The full doc comment to parse.
     * @param string $commentKey Unique identifier for this comment within the class (eg. p#myProperty, m#myMethod).
     */
    public function parseDocComment(\ReflectionClass $hostReflectionClass, string $docComment, string $commentKey): void
    {
        $this->hostReflectionClass = $hostReflectionClass;
        foreach ($this->getAnnotationList($docComment) ?? [] as $index => $annotationKvp) {
            $annotationName = $annotationKvp[0];
            $annotationValue = trim($annotationKvp[1]);
            $annotationClass = $this->aliasFinder->findClassForAlias($hostReflectionClass, $annotationName, false);
            try {
                $this->annotations[$hostReflectionClass->getName()][$commentKey][$annotationClass] = $this->convertValueToObject($annotationName, $annotationValue, $annotationClass);
            } catch (\Exception $ex) {
                //Cannot be hydrated as an object, so return generic
                $this->populateGenericAnnotation($annotationName, $annotationValue, $commentKey);
            }
        }
    }

    /**
     * Create and populate the AnnotationGeneric object to represent the annotation.
     * @param string $annotationName
     * @param string $annotationValue
     * @param string $commentKey
     */
    private function populateGenericAnnotation(
        string $annotationName,
        string $annotationValue, 
        string $commentKey
    ): void {
        //Create a closure to resolve type aliases
        $aliasFinder = function($alias) {
            return $this->aliasFinder->findClassForAlias($this->hostReflectionClass, $alias, false);
        };
        $generic = new AnnotationGeneric($annotationName, $annotationValue, $aliasFinder);

        //Make a note of it
        if (is_object($this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName] ?? null)) {
            //More than one, so convert to an array (eg. for multiple @property annotations on a class)
            $this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName] = [
                $this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName]
            ];
        }
        if (is_array($this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName] ?? null)) {
            //Add to the array
            $this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName][] = $generic;
        } else {
            //Only one so just assign it directly (easier for most use cases)
            $this->annotations[$this->hostReflectionClass->getName()][$commentKey][$annotationName] = $generic;
        }
    }

    /**
     * Pick out just the parts of the doc comment that are annotations.
     * @param string $docComment
     * @return array
     */
    private function getAnnotationList(string $docComment): array
    {
        $annotationList = [];
        $annotationStart = strpos($docComment, '@');
        if ($annotationStart !== false) {
            $allAnnotations = explode('@', substr($docComment, $annotationStart));
            foreach ($allAnnotations as $annotationString) {
                if ($annotationString) {
                    $key = '';
                    $value = '';
                    $this->extractAnnotationKeyValue($annotationString, $key, $value);
                    $annotationList[] = [$key, $value];
                }
            }
        }

        return $annotationList;
    }

    /**
     * Given an annotation string, extract the key and value, eg. given @MyAnnotation(abc="xyz"), the key would be
     * returned as MyAnnotation, and the value would be (abc="xyz").
     * @param string $annotationString
     * @param string $key
     * @param string $value
     */
    private function extractAnnotationKeyValue(string $annotationString, string &$key, string &$value): void
    {
        $keyFound = false;
        $lookForClosingBracket = false;
        $lastCharWasStar = false;
        for ($i = 0; $i < strlen($annotationString); $i++) {
            $char = substr($annotationString, $i, 1);
            if (!$keyFound) {
                $lookForClosingBracket = $lookForClosingBracket || $char == '(';
                $keyFound = ctype_space($char) || $lookForClosingBracket;
                $key .= $keyFound ? '' : $char;
            } elseif ($lookForClosingBracket && $char == ')') {
                $value = '(' . $value . ')';
                return;
            } elseif (!$lookForClosingBracket && ($char == '@' || ($char == '/' && $lastCharWasStar))) {
                //End of annotation
                return;
            } elseif ($char != '*') {
                $value .= $char;
            }
            $lastCharWasStar = $char == '*';
        }
    }

    /**
     * If this annotation goes by any other names (eg. due to use statements in the file), get a list of them.
     * @param string $annotationName
     * @return array
     */
    private function findAliasesForAnnotation(string $annotationName): array
    {
        if (!class_exists($annotationName)) {
            return ['@' . $annotationName];
        }

        //Find all possible aliases for this class name (fully qualified, use statement, or alias)
        $aliases = $this->aliasFinder->findAliasesForClass($this->hostReflectionClass, $annotationName);

        return preg_filter('/^/', '@', $aliases); //Prefix them all with @
    }

    /**
     * Convert annotation string value into an object.
     * @param $value
     * @param $className
     * @return mixed
     * @throws AnnotationReaderException
     * @throws \ReflectionException
     */
    private function convertValueToObject(string $annotation, string $value, string $className): ?object
    {
        $this->properties = $this->extractPropertyValues($value);
        $annotationReflectionClass = new \ReflectionClass($className);
        $constructor = $annotationReflectionClass->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            $mandatoryArgs = $this->getMandatoryConstructorArgs($annotation, $annotationReflectionClass);
            $object = new $className(...$mandatoryArgs);
        } else {
            $object = new $className();
        }

        foreach ($this->properties as $property => $propertyValue) {
            $this->setPropertyOnObject($object, $property, $propertyValue);
        }

        return $object;
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
        \ReflectionClass $annotationReflectionClass
    ): array {
        $mandatoryArgs = [];
        foreach ($annotationReflectionClass->getConstructor()->getParameters() as $constructorArg) {
            if (!$constructorArg->isOptional()) {
                if (!array_key_exists($constructorArg->getName(), $this->properties)) {
                    //We cannot create it!
                    $errorMessage = sprintf(
                        'Cannot create instance of annotation %1$s (defined on %2$s) because constructor argument %3$s is mandatory and has not been supplied (or the annotation is malformed so could not be parsed).',
                        $annotation,
                        $this->reflectionClass->getName(),
                        $constructorArg->getName()
                    );
                    throw new AnnotationReaderException($errorMessage);
                }
                $mandatoryArgs[] = $this->properties[$constructorArg->getName()];
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
        for ($i = 0; $i < strlen($value); $i++) {
            switch (substr($value, $i, 1)) {
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
            ['[', ']', '{', '}', ':', '\\\\', '    ', '', ''],
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
     * @param $object
     * @param $property
     * @param $propertyValue
     */
    private function setPropertyOnObject(object $object, string $property, $propertyValue): void
    {
        try {
            if (property_exists($object, $property)) {
                if (in_array($property, $this->classNameAttributes)) {
                    $propertyValue = $this->aliasFinder->findClassForAlias($this->hostReflectionClass, $propertyValue) ?: $propertyValue;
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
            }
        } catch (\Exception $ex) {}
    }
}
