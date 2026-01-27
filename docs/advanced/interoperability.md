# Async Bridging & Interoperability

Async Cache PHP uses a library-native `Future` object to remain agnostic of the underlying async library. However, in the real world, you are likely using **Guzzle Promises** or **ReactPHP**.

The library provides a built-in **Bridging Layer** to seamlessly convert between these formats.

## Automatic Conversion (Input)

When you pass a factory to the `wrap()` method, the library automatically detects what it returns.

```php
$manager->wrap('key', function () {
    // Case 1: Returning a Guzzle Promise (e.g., from an HTTP Client)
    return $client->getAsync('...');
    
    // Case 2: Returning a ReactPHP Promise
    return new \React\Promise\Promise(...);

    // Case 3: Returning a synchronous value
    return "simple string";
}, $options);
```

The `PromiseAdapter` automatically wraps these into an internal `Future`, so you don't need to change your existing async code.

## Manual Conversion (Output)

The `wrap()` method returns a `Fyennyi\AsyncCache\Core\Future`. If your application expects a specific promise type (e.g., to chain it with other Guzzle promises), you can convert it back using the `PromiseAdapter`.

### Converting to Guzzle Promise

```php
use Fyennyi\AsyncCache\Bridge\PromiseAdapter;

$future = $manager->wrap(...);

// Convert to GuzzleHttp\Promise\PromiseInterface
$guzzlePromise = PromiseAdapter::toGuzzle($future);

$guzzlePromise->then(function ($data) {
    echo "Handled by Guzzle logic: " . $data;
});
```

### Converting to ReactPHP Promise

```php
use Fyennyi\AsyncCache\Bridge\PromiseAdapter;

$future = $manager->wrap(...);

// Convert to React\Promise\PromiseInterface
$reactPromise = PromiseAdapter::toReact($future);

$reactPromise->then(function ($data) {
    echo "Handled by ReactPHP logic: " . $data;
});
```

## The `Future` Object

The internal `Future` object is lightweight and provides basic methods for result handling:

```php
$future = $manager->wrap(...);

// Non-blocking callback
$future->onResolve(
    function ($value) { /* Success */ },
    function ($error) { /* Failure */ }
);

// Blocking wait (synchronous)
// Uses ReactPHP's await() internally if needed
$result = $future->wait();
```
