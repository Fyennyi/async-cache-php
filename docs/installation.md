# Installation

The recommended way to install the library is via [Composer](https://getcomposer.org/).

## Requirements

- **PHP**: 8.1 or higher.
- **Composer**: For dependency management.

## Dependencies

This library uses [ReactPHP](https://reactphp.org/) for promise-based asynchronous operations. Key dependencies include:

- **react/promise**: Core promise library for async operations
- **clue/block-react**: Provides blocking `await()` function for synchronous contexts (tests, traditional PHP-FPM)
- **react/cache**: Async cache interface
- **react/promise-timer**: Promise timeout utilities
- **symfony/lock**: For atomic operations
- **symfony/rate-limiter**: For rate limiting support

## Installation

=== "Composer (Recommended)"

    Run the following command in your terminal:

    ```bash
    composer require fyennyi/async-cache-php
    ```

=== "Git / Manual"

    1. Clone the repository:
       ```bash
       git clone https://github.com/Fyennyi/async-cache-php.git
       cd async-cache-php
       ```

    2. Install dependencies:
       ```bash
       composer install
       ```

    3. Include the autoloader in your project:
       ```php
       require_once 'async-cache-php/vendor/autoload.php';
       ```

## Understanding clue/block-react

### What is clue/block-react?

[`clue/block-react`](https://github.com/clue/reactphp-block) is a lightweight library that provides integration between ReactPHP's promise-based async operations and traditional blocking/synchronous PHP code. It's particularly useful when you need to use async libraries in synchronous contexts.

### When to Use It

The `await()` function from `clue/block-react` is useful in these scenarios:

1. **Unit Tests**: When testing async code in a synchronous test environment
   ```php
   use function Clue\React\Block\await;
   
   $promise = $cache->wrap('key', fn() => fetchData());
   $result = await($promise); // Blocks until promise resolves
   ```

2. **Traditional PHP-FPM Applications**: When integrating async libraries into standard request-response cycles
   ```php
   public function handleRequest($request) {
       $promise = $this->asyncCache->wrap('user_' . $id, fn() => $this->fetchUser($id));
       return await($promise); // Returns the cached/fetched data
   }
   ```

3. **CLI Scripts**: When you need synchronous behavior in command-line tools

### How It Works

`clue/block-react` runs the ReactPHP event loop until a promise settles (resolves or rejects):

- **On Success**: Returns the resolved value
- **On Failure**: Throws the rejection reason as an exception

```php
use function Clue\React\Block\await;

try {
    $result = await($promise);
    echo "Success: $result";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Async vs Blocking Code

**Async (Recommended for long-running applications)**:
```php
$cache->wrap('key', fn() => $promise)->then(
    function ($result) {
        // Handle success
    },
    function ($error) {
        // Handle error
    }
);
```

**Blocking (Useful for tests and traditional PHP-FPM)**:
```php
use function Clue\React\Block\await;

$result = await($cache->wrap('key', fn() => $promise));
```

!!! tip "Performance Consideration"
    While `await()` is convenient, it blocks the entire PHP process. For long-running applications (like async servers), prefer promise chains with `.then()` callbacks to maintain non-blocking behavior.

## Post-Installation

Once installed, you can start using the library by including the Composer autoloader in your script:

```php
<?php

require 'vendor/autoload.php';

use Fyennyi\AsyncCache\AsyncCacheManager;
```