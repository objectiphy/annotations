<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * Represents a generic annotation, such as @var, @param (ie. one that is not named after a class).
 * @package Objectiphy\Annotations
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class AnnotationGeneric
{
    /** @var string Annotation name. */
    public string $name;

    /** @var string Raw annotation value as a string. */
    public string $value;

    /** @var array Where more than one word precedes a dollar sign, the words will be stored here. */
    public array $preVariableParts = [];

    /** @var string A single word either on its own or before a dollar sign will be treated as a data type. */
    public string $type;

    /** @var string First word to be prefixed with a dollar sign, if any. */
    public string $variable;

    /** @var string Remaining text is treated as a comment - any further parsing you'll have to do yourself! */
    public string $comment;
    
    private \closure $aliasFinder;

    /**
     * Attempts to split a generic annotation into constituent parts, resolving types if possible (ie. if there is a
     * dollar sign present, preceded by a single word, or there is a single word after the annotation name and nothing
     * else, and an alias resolving closure has been passed in - which is done automatically by the DocParser).
     * For example, if a closure was passed in that could resolve 'MyClass' to 'MyNamespace\MyClass', the following
     * annotation:
     *
     * @param MyClass $class Comment about this property
     *
     * ...would be returned as:
     *
     * AnnotationGeneric
     *   $name -> 'param'
     *   $value -> 'MyClass $class Comment about this property'
     *   $type -> 'MyNamespace\MyClass'
     *   $variable -> '$class'
     *   $comment -> 'Comment about this property'
     *
     * ...whereas:
     *
     * @some_random_annotation Some random comment
     *
     * ...would be returned as:
     *
     * AnnotationGeneric
     *   $name -> 'some_random_annotation'
     *   $value -> 'Some random comment'
     *   $comment -> 'Some random comment'
     *
     * ...and the following:
     *
     * @what_the_hell_is_this Several words here $variableName
     *
     * ...would be returned as:
     *
     * AnnotationGeneric
     *   $name -> 'what_the_hell_is_this'
     *   $value -> 'Several words here $variableName
     *   $variable -> '$variableName'
     *   $preVariableParts -> [
     *     'part_1' => 'Several',
     *     'part_2' => 'words',
     *     'part_3' => 'here'
     *   ]
     *
     * ...and this:
     *
     * @var MyClass
     *
     * ...would return:
     *
     * AnnotationGeneric
     *   $name -> 'var'
     *   $value -> 'MyClass words here $variableName
     *   $type -> 'MyNamespace\MyClass'
     *
     * @param string $name Name of the annotation.
     * @param string $value Full raw value of the annotation.
     * @param \closure $aliasFinder Closure that takes a single argument to resolve an alias into a class name.
     */
    public function __construct(string $name, string $value = '', \closure $aliasFinder)
    {
        $this->aliasFinder = $aliasFinder;
        $this->name = $name;
        $this->value = $value;
        $this->parseValue($value);
    }

    /**
     * Break down the annotation string into its constituent parts.
     * @param string $value
     */
    private function parseValue(string $value): void
    {
        $commentStart = 0;

        //Look for variable name in value - anything before is space separated, anything after is all one value
        $startOfVariable = strpos($value, '$');
        if ($startOfVariable !== false) {
            $before = $this->parseBeforeVariable($value);
            $endOfVariable = strpos($value, ' ', $startOfVariable);
            if ($endOfVariable === false) {
                $endOfVariable = strlen($value);
            }
            $this->variable = substr($value, $startOfVariable, $endOfVariable - $startOfVariable);
            $commentStart = $endOfVariable + 1;
        }

        if (strlen($value) > $commentStart) { // C'mere, there's more...
            $remainder = trim(substr($value, $commentStart));
            if ($startOfVariable === false && strpos($remainder, ' ') === false) {
                //Single word after the annotation name - try to resolve it to a class name
                $this->type = ($this->aliasFinder)($remainder);
            } else { //Assume anything left is a comment
                $this->comment = $remainder;
            }
        }
    }

    /**
     * Anything that comes before a dollar sign is separated by space, and if only one element is found, an attempt is
     * made to resolve it to a class name - otherwise just the raw value(s) are populated in the parts array.
     * @param string $value
     * @return string
     */
    private function parseBeforeVariable(string $value): string
    {
        $before = trim(substr($value, 0, strpos($value, '$')));
        if ($before) {
            $beforeParts = array_filter(explode(" ", $before));
            if (count($beforeParts) == 1) {
                $this->type = $this->aliasFinder ? ($this->aliasFinder)(trim($beforeParts[0])) : trim($beforeParts[0]);
            } else {
                $keys = preg_filter('/^/', 'part_', range(1, count($beforeParts)));
                $this->preVariableParts = $this->preVariableParts + array_combine($keys, $beforeParts);
            }
        }

        return $before;
    }
}
