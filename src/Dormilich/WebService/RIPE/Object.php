<?php
// Object.php

namespace Dormilich\WebService\RIPE;

/**
 * The prototype for every RIPE object class. 
 * 
 * A child class must
 *  1) define a constant named "PRIMARYKEY"
 *  2) set the class name to thats name (using camel case, e.g. domain => Domain, aut-num => AutNum)
 *  3) define the attributes for this RIPE object
 * 
 * A child class should
 *  - set the primary key on instantiation
 */
abstract class Object implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    // const PRIMARYKEY = '';

    /**
     * The type of the object as found in the WHOIS response object’s 'type' parameter.
     * @var string
     */
    protected $type;

    /**
     * Name-indexed array of attributes.
     * @var array 
     */
    protected $attributes = [];

    /**
     * Name-indexed array of auto-generated attributes, which must not be set by the user.
     * @var array 
     */
    protected $generated  = [];

    /**
     * Define the attributes for this object according to the RIPE DB docs.
     * 
     * @return void
     */
    abstract protected function init();

    /**
     * Get the value of the attribute defined as primary key.
     * 
     * Note: make sure the instantiated class contains a PRIMARYKEY constant.
     * 
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->getAttribute(static::PRIMARYKEY)->getValue();
    }

    /**
     * Get the name of the PK via function. 
     * Conformance function to overwrite in the Dummy class, 
     * which can not use a constant to store the PK.
     * 
     * @return string
     */
    public function getPrimaryKeyName()
    {
        return static::PRIMARYKEY;
    }

    /**
     * Shortcut for creating an attribute definition.
     * 
     * @param string $name Name of the attribute.
     * @param boolean $required If the attribute is mandatory.
     * @param boolean $multiple If the attribute allows multiple values.
     * @return void
     */
    protected function create($name, $required, $multiple)
    {
        $this->attributes[$name] = new Attribute($name, $required, $multiple);
    }

    /**
     * Shortcut for creating a generated attribute definition. Generated 
     * attributes are set to be optional.
     * 
     * @param string $name Name of the attribute.
     * @param boolean $multiple [false] If the attribute allows multiple values.
     * @return void
     */
    protected function generated($name, $multiple = Attribute::SINGLE)
    {
        $this->generated[$name]  = new Attribute($name, Attribute::OPTIONAL, $multiple);
    }

    /**
     * Get the name of the current RIPE object.
     * 
     * @return string RIPE object name.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get an attribute specified by name.
     * 
     * @param string $name Name of the attribute.
     * @return Attribute Attribute object.
     * @throws OutOfBoundsException Invalid argument name.
     */
    public function getAttribute($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
        if (isset($this->generated[$name])) {
            return $this->generated[$name];
        }
        throw new \OutOfBoundsException('Attribute "' . $name . '" is not defined for the ' . strtoupper($this->type) . ' object.');
    }

    /**
     * Set an attribute’s value(s).
     * 
     * @param string $name Attribute name.
     * @param mixed $value Attibute value(s).
     * @return self
     */
    public function setAttribute($name, $value)
    {
        $this->getAttribute($name)->setValue($value);

        return $this;
    }

    /**
     * Add a value to an attribute.
     * 
     * @param string $name Attribute name.
     * @param mixed $value Attibute value(s).
     * @return self
     */
    public function addAttribute($name, $value)
    {
        $this->getAttribute($name)->addValue($value);

        return $this;
    }

    /**
     * Get the array representation of all attributes that are populated with values.
     * 
     * @return array RIPE REST JSON compatible array.
     * @throws IncompleteRIPEObjectException A required attribute is empty.
     */
    protected function getAttributes()
    {
        $attributes = [];

        foreach ($this->attributes as $name => $attr) {
            if ($attr->isRequired() and !$attr->isDefined()) {
                throw new IncompleteRIPEObjectException('Required attribute ' . $attr->getName() . ' is not set.');
            }
            if ($attr->isDefined()) {
                // multiple attributes are serialised into separate entries 
                $attributes = array_merge($attributes, $attr->toArray());
            }
        }

        return $attributes;
    }

    /**
     * Convert object to a RIPE REST JSON compatible array.
     * 
     * @return type
     */
    public function toArray()
    {
        return [
            "source" => [
                "id" => $this->getAttribute('source')->getValue(), 
            ],
            "attributes" => [
                "attribute" => $this->getAttributes(), 
            ], 
        ];
    }

    /**
     * Output the object as a textual list of its defined attributes.
     * 
     * @return string
     */
    public function __toString()
    {
        $name   = get_class($this);
        $output = sprintf('%s (%s):'.\PHP_EOL, 
            substr($name, strrpos($name, '\\') + 1), 
            $this->getPrimaryKey()
        );

        foreach ($this as $name => $attr)  {
            foreach ((array) $attr->getValue() as $value) {
                $output .= sprintf('   %-20s %s'.\PHP_EOL, $name, $value);
            }
        }

        return $output;
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * 
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            "objects" => [
                "object" => [ $this->toArray() ], 
            ], 
        ];
    }

    /**
     * Checks if an Attribute exists, but not if it is populated.
     * 
     * @param mixed $offset The array key.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        $attributes = $this->attributes + $this->generated;
        return isset($attributes[$offset]); 
    }

    /**
     * Get the value of the specified Attribute.
     * 
     * @param string $offset Attribute name.
     * @return string|array Attribute value.
     * @throws OutOfBoundsException Attribute does not exist.
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset)->getValue();
    }

    /**
     * Set an Attibute’s value. Existing values will be replaced. 
     * For adding values use Object::addAttribute().
     * 
     * @param string $offset Attribute name.
     * @param type $value New Attribute value.
     * @return void
     * @throws OutOfBoundsException Attribute does not exist.
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Reset an Attribute’s value.
     * 
     * @param string $offset Attribute name.
     * @return void
     */
    public function offsetUnset($offset)
    {
        if (isset($this->attributes[$offset])) {
            $this->setAttribute($offset, []);
        }
    }

    /**
     * Create an Iterator for use in foreach. Only the populated Attributes are passed.
     * This creates a clone of the Attributes array and hence does not modify the original set.
     * 
     * @return ArrayIterator Read-only access to all defined attributes (including generated attributes)
     */
    public function getIterator()
    {
        return new \ArrayIterator(array_filter($this->attributes + $this->generated, function ($attr) {
            return $attr->isDefined();
        }));
    }

    /**
     * Return the number of defined Attributes (including generated ones).
     * 
     * @return integer
     */
    public function count()
    {
        return count(array_filter($this->attributes + $this->generated, function ($attr) {
            return $attr->isDefined();
        }));
    }

    /**
     * Check if any of the required Attributes is undefined.
     * 
     * @return boolean
     */
    public function isValid()
    {
        // generated attributes are never required …
        return array_reduce($this->attributes, function ($carry, $attr) {
            if ($attr->isRequired() and !$attr->isDefined()) {
                return false;
            }
            return $carry;
        }, true);
    }

    /**
     * Create a dummy object using the template descriptor from the RIPE 
     * metadata service. This can be useful if the package’s attribute 
     * validation rules become outdated and you absolutely need a confomant 
     * RIPE object (and can’t wait for the update).
     * 
     * @param string $type A RIPE object type
     * @param array $descriptor A template descriptor.
     * @return Dummy A dummy object according to the descriptor.
     */
    public static function factory($type, array $descriptor)
    {
        $key = null;
        foreach ($descriptor as $attribute) {
            if (in_array('PRIMARY_KEY', $attribute['keys'])) {
                $key = $attribute['name'];
                break;
            }
        }

        $obj = new Dummy($type, $key);

        foreach ($descriptor as $attribute) {
            $required = $attribute['requirement'] === 'MANDATORY';
            $multiple = $attribute['cardinality'] === 'MULTIPLE';
            $obj->setupAttribute($attribute['name'], $required, $multiple);
        }

        return $obj;
    }
}