# ⚠️ DEPRECATION WARNING: This Branch Uses Deprecated Package

## Critical Issue

This branch migrated from `react/async` to `clue/block-react`, which is the **WRONG DIRECTION**.

### The Facts

1. **`clue/block-react` is DEPRECATED** - It has been migrated to `react/async`
2. **`react/async` is the CURRENT package** - This is where active development happens
3. **This migration went backwards** - From current → deprecated

### Official Deprecation Notice from clue/block-react

> This package has now been migrated over to [reactphp/async](https://github.com/reactphp/async) and only exists for BC reasons.
> 
> Only the `await()` function has been merged without its optional parameters `$loop` and `$timeout`, the rest of `await()` works as-is from the latest `v1.5.0` release with no other significant changes. Simply update your code to use the updated namespace like this:
>
> ```php
> // old (deprecated)
> $result = Clue\React\Block\await($promise);
> 
> // new (current)
> $result = React\Async\await($promise);
> ```

Source: https://github.com/clue/reactphp-block

## What This Means

### Original Code Was Correct ✅

The codebase that used `react/async` was already using the **correct, modern package**. The migration to `clue/block-react` downgraded to a deprecated legacy package.

### Timeline of Events

1. **Before this branch**: Code used `react/async` (✅ correct)
2. **This branch**: Migrated to `clue/block-react` (❌ wrong - this is deprecated)
3. **Should be**: Continue using `react/async` (✅ correct)

## Why This Migration Doesn't Make Sense

### No Benefits, Only Downsides

| Aspect | react/async (current) | clue/block-react (deprecated) |
|--------|----------------------|------------------------------|
| **Status** | ✅ Active development | ❌ Deprecated, BC only |
| **Features** | ✅ Full feature set | ⚠️ Limited (only `await()`) |
| **Future** | ✅ Will receive updates | ❌ No future development |
| **PHP Support** | ✅ Modern (Fibers in PHP 8.1+) | ⚠️ Legacy implementation |
| **Performance** | ✅ Optimized with Fibers | ⚠️ Old event loop blocking |

### Technical Comparison

**react/async Features:**
- ✅ `async()` - Creates async functions using PHP 8.1+ Fibers
- ✅ `await()` - Blocks for promise resolution (modern implementation)
- ✅ `coroutine()` - Generator-based coroutines
- ✅ `delay()` - Non-blocking delays
- ✅ `parallel()` - Run promises in parallel
- ✅ `series()` - Run promises in sequence
- ✅ `waterfall()` - Chain dependent promises

**clue/block-react Features:**
- ⚠️ `await()` - Only this function, legacy implementation
- ⚠️ `awaitAny()` - Also available but deprecated
- ⚠️ `awaitAll()` - Also available but deprecated
- ❌ No `async()` wrapper
- ❌ No modern Fiber support

## Performance Implications

### react/async (Modern)
Uses PHP 8.1+ Fibers for true async/await without blocking the event loop in the same way:

```php
// Efficient: Uses Fibers
use function React\Async\await;
use function React\Async\async;

$result = await($promise); // Non-blocking at fiber level
```

### clue/block-react (Legacy)
Uses old-style event loop blocking:

```php
// Less efficient: Blocks event loop
use function Clue\React\Block\await;

$result = await($promise); // Blocks entire process
```

## What Should Happen

### Recommended Action: Revert This Branch ✅

The **correct solution** is to:

1. **Revert this branch** and return to using `react/async`
2. The original code was already correct
3. No migration is needed

### If You Must Use This Branch ⚠️

If for some reason you need to keep using `clue/block-react`:

1. **Be aware it's deprecated** - No future updates
2. **Plan to migrate back** to `react/async` eventually
3. **Understand the limitations** - Only basic `await()` functionality
4. **Monitor for deprecation warnings** - May be removed in future

## Migration Path Back to react/async

To revert to the correct, modern package:

### 1. Update composer.json

```diff
  "require": {
-     "clue/block-react": "^1.5",
+     "react/async": "^4.3",
-     "react/promise": "^3.0",
  }
```

### 2. Update Import Statements (14 test files)

```diff
- use function Clue\React\Block\await;
+ use function React\Async\await;
```

### 3. Update public/index.php

```diff
- // No async() available with clue/block-react
+ use function React\Async\async;

- return $manager->wrap('key', fn() => $promise)
-     ->then(fn($result) => Response::json(['data' => $result]));
+ return async(function() use ($manager) {
+     $result = await($manager->wrap('key', fn() => $promise));
+     return Response::json(['data' => $result]);
+ })();
```

### 4. Update Documentation

Remove all references to `clue/block-react` and replace with `react/async`.

## Conclusion

### Summary of the Issue

- ❌ This branch migrated to a **deprecated** package
- ✅ The original code using `react/async` was **already correct**
- 🔄 This migration should be **reverted** to use the modern package

### Questions to Answer

**"У чому сенс цього рефакторингу?"** (What's the point of this refactoring?)
- **Answer**: There is no point. This refactoring went in the wrong direction.

**"Можливо, якийсь приріст продуктивності?"** (Maybe some performance gain?)
- **Answer**: No, actually a performance **loss**. `react/async` uses modern Fibers, `clue/block-react` uses old event loop blocking.

**"А може більше контролю?"** (Or maybe more control?)
- **Answer**: No, **less** control. `react/async` has more features and active development.

### Final Recommendation

**Delete this branch and continue using `react/async`** as the original code did. It was already using the correct, modern, actively maintained package.

---

**Documentation Date**: 2026-01-30  
**Status**: This branch should not be merged  
**Action**: Recommend deletion per user's indication
