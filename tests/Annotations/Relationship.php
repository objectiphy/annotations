<?php

declare(strict_types=1);

//This file has been copied from an early version of Objectiphy, just as an example to use in unit tests
namespace Objectiphy\Annotations\Tests\Annotations;

use Objectiphy\Annotations\AttributeTrait;

/**
 * An alternative to the various Doctrine relationship annotations (if specified, this will take precedence over 
 * Doctrine).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)] //Would not be repeatable irl in objectiphy, but ok for test
class Relationship
{
    use AttributeTrait;
    
    const ONE_TO_ONE = 'one_to_one';
    const ONE_TO_MANY = 'one_to_many';
    const MANY_TO_ONE = 'many_to_one';
    const MANY_TO_MANY = 'many_to_many';

    /** @var bool Whether this relationship is part of the primary key. */
    public bool $isPrimaryKey = false;

    /** @var string One of the class constant values (eg. "one_to_one"). */
    public string $relationshipType;

    /**
     * @var string Child entity class name.
     * NOTE: This has been converted to private with getter/setter for testing purposes.
     */
    private string $childClassName = '';

    /**
     * @var string If the relationship is owned by the other class, specify the property on the other class that holds
     * the mapping.
     */
    public string $mappedBy = '';
    
    /**
     * @var boolean Whether or not to lazy load the data (for a child object if this property is the owning side)
     * - defaults to false for -to-one associations, and true for -to-many associations (ie. if this value is null).
     */
    public ?bool $lazyLoad = null;
    
    /**
     * @var string Name of target table (child entity) - defaults to the Objectiphy table annotation value on the
     * child class, if applicable. Also used to join to another table for scalar values that are not child objects.
     */
    public string $joinTable = '';
    
    /** @var string Name of column to join with on the target table (child entity). */
    public string $joinColumn = '';
    
    /** @var string Name of column to join with on the source table (parent entity). */
    public string $sourceJoinColumn = '';
    
    /** @var string "INNER" or "LEFT". */
    public string $joinType = 'LEFT';
    
    /** @var string Custom SQL for join (eg. "vehicle.policy_id = policy.id"). */
    public string $joinSql = '';
    
    /** @var bool Whether this is actually an embedded (value) object that maps to several columns */
    public bool $isEmbedded = false;
    
    /** @var string Prefix to apply to embedded object column names */
    public string $embeddedColumnPrefix = '';
    
    /** @var array Properties to order children by */
    public array $orderBy = [];
    
    /**
     * @var string Class to use for collections of entities (must be traversable and take an array in constructor) -
     * applies to properties with a to-many relationship only.
     */
    public string $collectionType = 'array';
    
    /** @var bool Cascade deletes (if parent object is deleted, delete any children also) */
    public bool $cascadeDeletes = false;
    
    /** @var bool Orphan control (if child is removed from parent, delete the child, not just the relationship) */
    public bool $orphanRemoval = false;
    
    public function setChildClassName($value)
    {
        $this->childClassName = $value;
    }

    public function getChildClassName()
    {
        return $this->childClassName;
    }
}
