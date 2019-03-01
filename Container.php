<?php

namespace wpscholar;

/**
 * Class Container
 *.
 * @package wpscholar
 */
class Container implements \ArrayAccess, \Countable, \Iterator {

	/**
	 * Internal storage of items.
	 *
	 * @var array
	 */
	protected $items = [];

	/**
	 * Internal storage of class instances.
	 *
	 * @var array
	 */
	protected $instances = [];

	/**
	 * Internal storage of factory closures.
	 *
	 * @var \SplObjectStorage
	 */
	protected $factories;

	/**
	 * Internal storage of storage closures.
	 *
	 * @var \SplObjectStorage
	 */
	protected $services;

	/**
	 * Internal pointer used for iteration.
	 *
	 * @var int
	 */
	protected $pointer = 0;

	/**
	 * Container constructor.
	 *
	 * @param array $items Initial items to store in the container
	 */
	public function __construct( array $items = [] ) {
		$this->reset();
		$this->items = $items;
	}

	/**
	 * Checks if an array key exists.
	 *
	 * @param string $key Item key
	 *
	 * @return bool
	 */
	public function has( $key ) {
		return array_key_exists( $key, $this->items );
	}

	/**
	 * Get an array value by key.
	 *
	 * @throws \InvalidArgumentException If key is not found.
	 *
	 * @param string $key Item key
	 *
	 * @return mixed
	 */
	public function get( $key ) {

		// Return instance, if available
		if ( isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		// Get raw value
		$value = $this->raw( $key );

		// If this is a factory, return a new instance
		if ( $this->isFactory( $value ) ) {
			return $value( $this );
		}

		// If this is a service, return a single instance
		if ( $this->isService( $value ) ) {
			$this->instances[ $key ] = $value( $this );

			return $this->instances[ $key ];
		}

		return $value;

	}

	/**
	 * Set an array value by key.
	 *
	 * @param string $key Item key
	 * @param mixed  $value Item value
	 *
	 * @return $this
	 */
	public function set( $key, $value ) {
		$this->items[ $key ] = $value;

		return $this;
	}

	/**
	 * Unset an array value by key.
	 *
	 * @param string $key Item key
	 *
	 * @return $this
	 */
	public function delete( $key ) {
		if ( $this->has( $key ) ) {
			$value = $this->get( $key );
			if ( $this->isFactory( $value ) ) {
				$this->factories->detach( $value );
			} elseif ( $this->isService( $value ) ) {
				$this->services->detach( $value );
			}
			unset( $this->items[ $key ], $this->instances[ $key ] );
		}

		return $this;
	}

	/**
	 * Remove an instance.
	 *
	 * @param string $key Item key
	 *
	 * @return $this
	 */
	public function deleteInstance( $key ) {
		unset( $this->instances[ $key ] );

		return $this;
	}

	/**
	 * Remove all instances.
	 *
	 * @return $this
	 */
	public function deleteAllInstances() {
		$this->instances = [];

		return $this;
	}

	/**
	 * Get all array keys.
	 *
	 * @return array
	 */
	public function keys() {
		return array_keys( $this->items );
	}

	/**
	 * Get a raw value by key.
	 *
	 * @throws \InvalidArgumentException If key is not found.
	 *
	 * @param string $key Item key
	 *
	 * @return mixed
	 */
	public function raw( $key ) {
		if ( ! $this->has( $key ) ) {
			throw new \InvalidArgumentException( sprintf( 'Identifier "%s" is not defined.', $key ) );
		}

		return $this->items[ $key ];
	}

	/**
	 * Marks a callable as a factory.
	 *
	 * @param \Closure $closure Closure that returns a new class instance
	 *
	 * @return \Closure
	 */
	public function factory( \Closure $closure ) {
		$this->factories->attach( $closure );

		return $closure;
	}

	/**
	 * Checks if a value is a factory.
	 *
	 * @param object $item Item to check
	 *
	 * @return bool
	 */
	public function isFactory( $item ) {
		return is_object( $item ) && isset( $this->factories[ $item ] );
	}

	/**
	 * Marks a callable as a service.
	 *
	 * @param \Closure $closure Closure that returns a new instance (only called once)
	 *
	 * @return \Closure
	 */
	public function service( \Closure $closure ) {
		$this->services->attach( $closure );

		return $closure;
	}

	/**
	 * Checks if a value is a service.
	 *
	 * @param object $item Item to check
	 *
	 * @return bool
	 */
	public function isService( $item ) {
		return is_object( $item ) && isset( $this->services[ $item ] );
	}

	/**
	 * Extenda a factory or service by creating a closure that will manipulate the instantiated instance.
	 *
	 * @throws \InvalidArgumentException If key is not found.
	 * @throws \RuntimeException If item is not a service or factory.
	 *
	 * @param string   $key Item key
	 * @param \Closure $closure Closure that extends a class instance
	 *
	 * @return \Closure
	 */
	public function extend( $key, \Closure $closure ) {

		// Get the existing raw value
		$value = $this->raw( $key );

		// If the value isn't a factory or service, throw an exception.
		if ( ! $this->isService( $value ) && ! $this->isFactory( $value ) ) {
			throw new \RuntimeException( sprintf( 'Identifier "%s" does not contain an object definition.', $key ) );
		}

		// Create a new closure that extends the existing one.
		$extended = function ( Container $container ) use ( $closure, $value ) {
			return $closure( $value( $container ), $container );
		};

		if ( $this->isFactory( $value ) ) {

			// Replace factory object
			$this->factories->detach( $value );
			$this->factories->attach( $extended );

		} elseif ( $this->isService( $value ) ) {

			// Replace service object
			$this->services->detach( $value );
			$this->services->attach( $extended );

		}

		// Replace object in items array
		$this->items[ $key ] = $extended;

		return $extended;
	}

	/**
	 * Reset everything
	 *
	 * @return $this
	 */
	public function reset() {
		$this->items     = [];
		$this->instances = [];
		$this->factories = new \SplObjectStorage();
		$this->services  = new \SplObjectStorage();

		return $this;
	}

	/**
	 * Get the number of items.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->items );
	}

	/**
	 * Checks if an array key exists.
	 *
	 * @param string $key Item key
	 *
	 * @return bool
	 */
	public function offsetExists( $key ) {
		return $this->has( $key );
	}

	/**
	 * Get an array value by key.
	 *
	 * @param string $key Item key
	 *
	 * @return mixed
	 */
	public function offsetGet( $key ) {
		return $this->get( $key );
	}

	/**
	 * Set an array value by key.
	 *
	 * @param string $key  Item key
	 * @param mixed  $value   Item value
	 */
	public function offsetSet( $key, $value ) {
		$this->set( $key, $value );
	}

	/**
	 * Unset an array value by key.
	 *
	 * @param string $key Item key
	 */
	public function offsetUnset( $key ) {
		$this->delete( $key );
	}

	/**
	 * Move to next item.
	 */
	public function next() {
		++ $this->pointer;
	}

	/**
	 * Get current item.
	 *
	 * @return mixed
	 */
	public function current() {
		return $this->offsetGet( $this->key() );
	}

	/**
	 * Get current key.
	 *
	 * @return string
	 */
	public function key() {
		return $this->keys()[ $this->pointer ];
	}

	/**
	 * Rewind
	 */
	public function rewind() {
		$this->pointer = 0;
	}

	/**
	 * Check if pointer is valid.
	 *
	 * @return bool
	 */
	public function valid() {
		return isset( $this->keys()[ $this->pointer ] );
	}

}
