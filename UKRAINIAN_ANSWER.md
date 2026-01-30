# Відповідь на питання про рефакторинг (Answer to Refactoring Questions)

## Ваші питання / Your Questions

> «А що таке?» — запитую я в себе. Я тільки що прочитав Deprecation notice
> This package has now been migrated over to reactphp/async and only exists for BC reasons.
> У чому сенс цього рефакторингу? Це мене треба запитати. Можливо, якийсь приріст продуктивності? А може більше контролю?

## Відповіді / Answers

### У чому сенс цього рефакторингу? (What's the point of this refactoring?)

**Відповідь: Немає сенсу.** (Answer: There is no point.)

Цей рефакторинг пішов **у неправильному напрямку**. Він мігрував з сучасного пакету на застарілий:

- ❌ `clue/block-react` - **ЗАСТАРІЛИЙ** (deprecated), підтримка тільки для зворотної сумісності
- ✅ `react/async` - **СУЧАСНИЙ** (current), активна розробка

This refactoring went in the **wrong direction**. It migrated from the modern package to the deprecated one:

- ❌ `clue/block-react` - **DEPRECATED**, only exists for BC
- ✅ `react/async` - **CURRENT**, active development

### Можливо, якийсь приріст продуктивності? (Maybe some performance gain?)

**Відповідь: Ні, навпаки - втрата продуктивності.** (Answer: No, actually performance loss.)

| Характеристика | react/async | clue/block-react |
|----------------|-------------|------------------|
| Технологія | ✅ PHP 8.1+ Fibers (сучасно) | ❌ Старий event loop |
| Продуктивність | ✅ Вища | ❌ Нижча |
| Блокування | ✅ На рівні Fiber | ❌ Блокує весь процес |

`react/async` використовує сучасні Fibers з PHP 8.1+, що дає кращу продуктивність.  
`react/async` uses modern PHP 8.1+ Fibers for better performance.

### А може більше контролю? (Or maybe more control?)

**Відповідь: Ні, менше контролю.** (Answer: No, less control.)

#### react/async - Більше функцій (More features):
- ✅ `async()` - створення async функцій
- ✅ `await()` - блокування для промісів
- ✅ `coroutine()` - корутини на генераторах
- ✅ `delay()` - неблокуючі затримки
- ✅ `parallel()` - паралельне виконання
- ✅ `series()` - послідовне виконання
- ✅ `waterfall()` - ланцюжок залежних промісів

#### clue/block-react - Обмежений функціонал (Limited):
- ⚠️ `await()` - **тільки ця функція**
- ⚠️ `awaitAny()` - також є, але застарілий
- ⚠️ `awaitAll()` - також є, але застарілий
- ❌ Немає `async()`
- ❌ Немає підтримки Fibers

## Офіційне повідомлення про застарілість / Official Deprecation Notice

З GitHub сторінки `clue/block-react`:

> **Deprecation notice**
> 
> This package has now been migrated over to [reactphp/async](https://github.com/reactphp/async) and only exists for BC reasons.
> 
> composer require "react/async:^4 || ^3 || ^2"
> 
> Only the `await()` function has been merged without its optional parameters `$loop` and `$timeout`, the rest of `await()` works as-is from the latest `v1.5.0` release with no other significant changes. Simply update your code to use the updated namespace like this:
> 
> ```php
> // old
> $result = Clue\React\Block\await($promise);
> 
> // new  
> $result = React\Async\await($promise);
> ```

Джерело: https://github.com/clue/reactphp-block

## Висновок / Conclusion

### Що сталося (What happened):

1. **Початковий код** - використовував `react/async` ✅ (правильно)
2. **Цей рефакторинг** - змінив на `clue/block-react` ❌ (неправильно - це застарілий пакет)
3. **Що треба робити** - повернутися до `react/async` ✅ (правильно)

1. **Original code** - used `react/async` ✅ (correct)
2. **This refactoring** - changed to `clue/block-react` ❌ (wrong - deprecated package)
3. **What should be done** - return to `react/async` ✅ (correct)

### Рекомендація / Recommendation

**Ви маєте рацію** - цю гілку треба видалити.  
**You are right** - this branch should be deleted.

Початковий код, який використовував `react/async`, був **вже правильний** і не потребував міграції.  
The original code using `react/async` was **already correct** and didn't need migration.

## Технічні деталі / Technical Details

Детальну технічну інформацію дивіться в:  
For detailed technical information, see:

- `DEPRECATION_WARNING.md` - повне пояснення проблеми (full explanation)
- `docs/concepts/blocking-operations.md` - оновлено з попередженнями (updated with warnings)
- `docs/installation.md` - оновлено з попередженнями (updated with warnings)
- `README.md` - оновлено з попередженнями (updated with warnings)

---

**Дата**: 2026-01-30  
**Статус**: Цю гілку не треба мерджити / This branch should not be merged  
**Дія**: Видалення гілки (як ви й планували) / Delete branch (as you planned)
