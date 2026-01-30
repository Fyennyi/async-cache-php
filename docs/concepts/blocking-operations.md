# Blocking Operations with clue/block-react

## Overview

This library is built on ReactPHP promises, which are inherently asynchronous and non-blocking. However, there are scenarios where you need to integrate async operations into synchronous (blocking) code. This is where `clue/block-react` comes in.

## What is clue/block-react?

[`clue/block-react`](https://github.com/clue/reactphp-block) is a lightweight library that bridges the gap between ReactPHP's promise-based async world and traditional blocking PHP code. It provides utility functions to wait for promises to settle in synchronous contexts.

### Key Function: await()

The main function you'll use is `await()`, which blocks execution until a promise resolves or rejects:

```php
use function Clue\React\Block\await;
use React\Promise\PromiseInterface;

// This blocks until the promise settles
$result = await($promise);
```

## When to Use Blocking Operations

### ✅ Good Use Cases

1. **Unit Tests**
   ```php
   public function testCacheHit(): void
   {
       $promise = $this->manager->wrap('key', fn() => 'value', $options);
       $result = await($promise);
       
       $this->assertSame('value', $result);
   }
   ```

2. **Traditional PHP-FPM / Apache mod_php Applications**
   ```php
   // In a Laravel/Symfony controller
   public function show($id)
   {
       $user = await($this->cache->wrap(
           "user_{$id}",
           fn() => $this->userService->fetchAsync($id),
           new CacheOptions(ttl: 3600)
       ));
       
       return view('user.show', compact('user'));
   }
   ```

3. **CLI Scripts and Commands**
   ```php
   // In a console command
   protected function execute(InputInterface $input, OutputInterface $output)
   {
       $data = await($this->cache->wrap('report_data', fn() => $this->generateReport()));
       $output->writeln("Report: " . $data);
       return Command::SUCCESS;
   }
   ```

### ❌ Avoid in These Cases

1. **Long-Running Async Servers**
   ```php
   // ❌ BAD: Blocks the entire server
   $http = new HttpServer(function ($request) use ($cache) {
       $result = await($cache->wrap('key', fn() => fetchData()));
       return new Response(200, [], $result);
   });
   
   // ✅ GOOD: Non-blocking
   $http = new HttpServer(function ($request) use ($cache) {
       return $cache->wrap('key', fn() => fetchData())
           ->then(fn($result) => new Response(200, [], $result));
   });
   ```

2. **Event Loop Callbacks**
   ```php
   // ❌ BAD: Blocks event loop
   $loop->addPeriodicTimer(1.0, function() use ($cache) {
       $data = await($cache->wrap('key', fn() => fetchData()));
       processData($data);
   });
   
   // ✅ GOOD: Non-blocking
   $loop->addPeriodicTimer(1.0, function() use ($cache) {
       $cache->wrap('key', fn() => fetchData())
           ->then(fn($data) => processData($data));
   });
   ```

## How await() Works Internally

The `await()` function:

1. Checks if a ReactPHP event loop is running
2. Attaches handlers to the promise
3. Runs the event loop until the promise settles
4. Returns the resolved value or throws the rejection reason

```php
use function Clue\React\Block\await;

try {
    $result = await($promise);
    // Promise resolved successfully
    return $result;
} catch (\Throwable $e) {
    // Promise was rejected
    throw $e;
}
```

## Performance Considerations

### Blocking Overhead

When you use `await()`, the entire PHP process is blocked:

- ✅ **Acceptable**: Short-lived requests (< 1 second) in PHP-FPM
- ⚠️ **Caution**: Multiple sequential `await()` calls can add up
- ❌ **Avoid**: Long-running processes or async servers

### Optimization Strategies

**Sequential Blocking (Slow)**:
```php
// Takes 3 seconds total if each request takes 1 second
$user = await($cache->wrap('user', fn() => fetchUser()));      // 1s
$posts = await($cache->wrap('posts', fn() => fetchPosts()));   // 1s
$comments = await($cache->wrap('comments', fn() => fetchComments())); // 1s
```

**Concurrent with Single await() (Fast)**:
```php
use React\Promise;

// Takes ~1 second total - all requests run in parallel
$promises = [
    'user' => $cache->wrap('user', fn() => fetchUser()),
    'posts' => $cache->wrap('posts', fn() => fetchPosts()),
    'comments' => $cache->wrap('comments', fn() => fetchComments()),
];

$results = await(Promise\all($promises)); // Only one await() call
// $results = ['user' => ..., 'posts' => ..., 'comments' => ...]
```

## Comparison: Async vs Blocking

| Aspect | Async (Promises) | Blocking (await) |
|--------|------------------|------------------|
| **Execution** | Non-blocking, concurrent | Blocking, sequential |
| **Best for** | Long-running apps, servers | Tests, traditional PHP |
| **Syntax** | `.then()` chains | Try-catch blocks |
| **Performance** | High throughput | Limited by blocking |
| **Complexity** | Higher (callbacks) | Lower (linear code) |

## Examples

### Example 1: Testing with await()

```php
use PHPUnit\Framework\TestCase;
use function Clue\React\Block\await;

class CacheTest extends TestCase
{
    public function testCacheStoresAndRetrieves(): void
    {
        $manager = $this->createManager();
        
        // First call - cache miss
        $result1 = await($manager->wrap('test_key', fn() => 'test_value'));
        $this->assertSame('test_value', $result1);
        
        // Second call - cache hit
        $result2 = await($manager->wrap('test_key', fn() => 'different_value'));
        $this->assertSame('test_value', $result2); // Returns cached value
    }
}
```

### Example 2: Laravel Controller with await()

```php
namespace App\Http\Controllers;

use Fyennyi\AsyncCache\AsyncCacheManager;
use function Clue\React\Block\await;

class ProductController extends Controller
{
    public function __construct(
        private AsyncCacheManager $cache
    ) {}
    
    public function show($id)
    {
        $product = await($this->cache->wrap(
            "product_{$id}",
            fn() => $this->fetchProductFromApi($id),
            new CacheOptions(ttl: 3600)
        ));
        
        return view('products.show', compact('product'));
    }
    
    private function fetchProductFromApi($id): PromiseInterface
    {
        // Returns a promise from HTTP client
        return $this->httpClient->get("/products/{$id}")
            ->then(fn($response) => json_decode($response->getBody(), true));
    }
}
```

### Example 3: Async Server (No await)

```php
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

$http = new HttpServer(function ($request) use ($cache) {
    $path = $request->getUri()->getPath();
    
    if ($path === '/api/product') {
        // ✅ Non-blocking: returns promise directly
        return $cache->wrap('featured_product', fn() => fetchProduct())
            ->then(
                fn($product) => Response::json($product),
                fn($error) => Response::json(['error' => $error->getMessage()], 500)
            );
    }
    
    return new Response(404);
});

$socket = new SocketServer('0.0.0.0:8080');
$http->listen($socket);

echo "Server running on http://0.0.0.0:8080\n";
```

## Migration from react/async

Previously, this library used `react/async` which provided fiber-based `await()`. We've migrated to `clue/block-react` which provides the same functionality without requiring fibers:

**Before (react/async)**:
```php
use function React\Async\await;

$result = await($promise);
```

**After (clue/block-react)**:
```php
use function Clue\React\Block\await;

$result = await($promise);
```

The API is identical - only the import statement changes.

## Additional Resources

- [clue/block-react GitHub](https://github.com/clue/reactphp-block)
- [ReactPHP Promises Documentation](https://github.com/reactphp/promise)
- [Understanding Async vs Blocking](https://reactphp.org/promise/#blocking)

## Summary

- **clue/block-react** bridges async promises and blocking code
- Use `await()` for tests and traditional PHP applications
- Avoid `await()` in long-running async servers
- Combine multiple promises before using `await()` for better performance
- The function works identically to the old `React\Async\await()`
