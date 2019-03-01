# Container

A lightweight PHP 5.4+ compatible dependency injection container.

## Usage

Basic manipulation of items.

```php
<?php

// Create a new instance
$container = new wpscholar\Container();

// Set a value
$container->set('email', 'webmaster@site.com');

// Check if a value exists
$exists = $container->has('email');

// Get a value
$value = $container->get('email');

// Delete a value
$container->delete('email');
```

Basic manipulation of items using array syntax.

```php
<?php

// Create a new instance
$container = new wpscholar\Container();

// Set a value
$container['email'] = 'webmaster@site.com';

// Check if a value exists
$exists = isset( $container['email'];

// Get a value
$value = $container['email'];

// Delete a value
unset( $container['email'] );
```

Register a factory. Factories return a new class instance every time you fetch them.

```php
<?php

use wpscholar\Container;

// Create a new instance
$container = new Container();

// Add a factory
$container->set( 'session', $container->factory( function( Container $c ) {
    return new Session( $c->get('session_id') );
} ) );

// Get a factory
$factory = $container->get( 'session' );

// Check if an item is a factory
$isFactory = $container->isFactory( $factory );
```

Register a service. Services return the same class instance every time you fetch them.

```php
<?php

use wpscholar\Container;

// Create a new instance
$container = new Container();

// Add a service
$container->set( 'session', $container->service( function( Container $c ) {
    return new Session( $c->get('session_id') );
} ) );

// Get a service
$service = $container->get( 'session' );

// Check if an item is a service
$isService = $container->isService( $service );
```

Extend a previously registered factory or service.

```php
<?php

use wpscholar\Closure;

$container->extend( 'session', function( $instance, Closure $c ) {

    $instance->setShoppingCart( $c->get('shopping_cart') );

    return $instance;
} );

```