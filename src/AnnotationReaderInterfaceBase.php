<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * If, for some reason I haven't thought of yet, someone wanted to use this instead of the Doctrine annotation reader, 
 * we'll make it compatible by implementing the same interface.
 */
if (interface_exists('Doctrine\Common\Annotations\Reader')) {
    interface AnnotationReaderInterfaceBase extends \Doctrine\Common\Annotations\Reader {}
} else {
    interface AnnotationReaderInterfaceBase
    {
        /**
         * For Doctrine compatibility - get all annotations found on a class.
         * @param \ReflectionClass $class
         * @return array An array of annotation objects.
         */
        public function getClassAnnotations(\ReflectionClass $class);

        /**
         * For Doctrine compatibility - get a particular annotation from a class.
         * @param \ReflectionClass $class
         * @param $annotationName
         * @return object|null The annotation object.
         */
        public function getClassAnnotation(\ReflectionClass $class, $annotationName);

        /**
         * For Doctrine compatibility - get all annotations found on a method.
         * @return array An array of annotation objects.
         */
        public function getMethodAnnotations(\ReflectionMethod $method);

        /**
         * For Doctrine compatibility - get a particular annotation from a method.
         * @return object|null The annotation object.
         */
        public function getMethodAnnotation(\ReflectionMethod $method, $annotationName);

        /**
         * For Doctrine compatibility -  get all annotations found on a property.
         * @return array An array of annotations objects.
         */
        public function getPropertyAnnotations(\ReflectionProperty $property);

        /**
         * For Doctrine compatibility - get a particular annotation from a property.
         * @return object|null The annotation object.
         */
        public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName);
    }
}
