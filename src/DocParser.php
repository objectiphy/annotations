<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

class DocParser
{
    private array $classAnnotations;
    private array $propertyAnnotations;
    private array $methodAnnotations;
    
    public function getClassAnnotations(\ReflectionClass $reflectionClass)
    {
        $class = $reflectionClass->getName();
        if (empty($this->classAnnotations[$class])) {
            $this->classAnnotations[$class] = $this->parseDocComment($reflectionClass->getDocComment());
        }
        
        return $this->classAnnotations[$class];
    }

    public function getPropertyAnnotations(\ReflectionClass $reflectionClass)
    {
        $class = $reflectionClass->getName();
        if (empty($this->propertyAnnotations[$class])) {
            $this->propertyAnnotations[$class] = [];
            foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                $docComment = $reflectionProperty->getDocComment();
                $this->propertyAnnotations[$class][$reflectionProperty->getName()] = $this->parseDocComment($docComment);
            }
        }
        
        return $this->propertyAnnotations[$class];
    }

    public function getMethodAnnotations(\ReflectionClass $reflectionClass)
    {
        $class = $reflectionClass->getName();
        if (empty($this->methodAnnotations[$class])) {
            $this->methodAnnotations[$class] = [];
            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                $docComment = $reflectionMethod->getDocComment();
                $this->methodAnnotations[$class][$reflectionMethod->getName()] = $this->parseDocComment($docComment);
            }
        }

        return $this->methodAnnotations[$class];
    }

    private function parseDocComment(string $docComment)
    {
        $annotations = [];
        foreach ($this->getAnnotationList($docComment) ?? [] as $index => $annotationKvp) {
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
