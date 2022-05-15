<?php

namespace Objectiphy\Annotations;

/**
 * Attributes must accept all properties as arguments to the constructor
 */
trait AttributeTrait
{
    public function __construct(...$args)
    {
        foreach ($args as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }
}
