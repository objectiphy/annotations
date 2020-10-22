<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Extracts annotations from doc comments as a simple key/value pair
 * (key = annotation name, value = annotation as a string)
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class DocParser
{
    //Local cache
    private array $classAnnotations;
    private array $propertyAnnotations;
    private array $methodAnnotations;

    /**
     * Parse doc comment for class annotations
     * @param \ReflectionClass $reflectionClass
     * @return array Array of annotation strings, keyed by annotation name.
     */
    public function getClassAnnotations(\ReflectionClass $reflectionClass): array
    {
        $class = $reflectionClass->getName();
        if (empty($this->classAnnotations[$class])) {
            $docComment = $reflectionClass->getDocComment() ?: '';
            $this->classAnnotations[$class] = $this->parseDocComment($docComment);
        }
        
        return $this->classAnnotations[$class] ?? [];
    }

    /**
     * Parse doc comments for all property annotations, keyed by property name
     * @param \ReflectionClass $reflectionClass
     * @return array Array of annotation strings, keyed by property name, then annotation name.
     */
    public function getPropertyAnnotations(\ReflectionClass $reflectionClass): array
    {
        $class = $reflectionClass->getName();
        if (empty($this->propertyAnnotations[$class])) {
            $this->propertyAnnotations[$class] = [];
            foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                $docComment = $reflectionProperty->getDocComment() ?: '';
                $property = $reflectionProperty->getName();
                $parsedComment = $this->parseDocComment($docComment);
                $this->propertyAnnotations[$class][$property] = $parsedComment;
            }
        }
        
        return $this->propertyAnnotations[$class];
    }

    /**
     * Parse doc comments for all method annotations, keyed by method name
     * @param \ReflectionClass $reflectionClass
     * @return array Array of annotation strings, keyed by method name, then annotation name.
     */
    public function getMethodAnnotations(\ReflectionClass $reflectionClass): array
    {
        $class = $reflectionClass->getName();
        if (empty($this->methodAnnotations[$class])) {
            $this->methodAnnotations[$class] = [];
            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                $docComment = $reflectionMethod->getDocComment() ?: '';
                $method = $reflectionMethod->getName();
                $parsedComment = $this->parseDocComment($docComment);
                $this->methodAnnotations[$class][$method] = $parsedComment;
            }
        }

        return $this->methodAnnotations[$class];
    }

    /**
     * Compile list of annotations for the given doc comment, keyed by annotation name.
     * @param string $docComment
     * @return array
     */
    private function parseDocComment(string $docComment): array
    {
        $annotations = [];
        $annotationList = $this->getAnnotationList($docComment) ?? [];
        foreach ($annotationList as $index => $annotationKvp) {
            $annotationName = $annotationKvp[0];
            $annotationValue = trim($annotationKvp[1]);
            $annotations[] = [$annotationName => $annotationValue];
        }

        return $annotations;
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
        
        foreach (str_split($annotationString) as $char) { //Faster than traversing the string
            if (!$keyFound) {
                $lookForClosingBracket = $lookForClosingBracket || $char == '(';
                $keyFound = ctype_space($char) || $lookForClosingBracket;
                $key .= $keyFound ? '' : $char;
            } elseif ($lookForClosingBracket && $char == ')') {
                $value = '(' . $value . ')';
                return;
            } elseif (!$lookForClosingBracket && $char == '/' && $lastCharWasStar) {
                //End of annotation
                return;
            } elseif ($char != '*') {
                $value .= $char;
            }
            $lastCharWasStar = $char == '*';
        }
    }
}
