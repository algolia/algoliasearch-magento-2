# Plugin Testing Guide

## Overview

Plugins (interceptors) wrap Magento methods via `before*`, `after*`, and `around*` conventions. Tests must exercise the plugin logic itself — not the wrapped method.

## What to test

- **`before*` plugins**: verify that the arguments passed back are modified correctly, or that side effects (e.g. `addCommitCallback`) are triggered under the right conditions.
- **`after*` plugins**: verify that the return value is transformed or left unchanged under different conditions; verify guard clauses.
- **`around*` plugins**: verify that `$proceed` is called (or not) and that the wrapped result is handled correctly; treat `$proceed` as a mock callable.

## Skip these

- Trivial `after*` plugins that apply a single, unconditional transformation with no guard clause — these just return what we mock.
- `before*` plugins that unconditionally pass arguments through without modification.

## `addCommitCallback` pattern

Many plugins defer work by calling `$stockItem->addCommitCallback(function() use (...) { ... })`. Capture and invoke the closure inline so the deferred logic is actually tested:

```php
$subject->expects($this->once())
    ->method('addCommitCallback')
    ->with($this->isType('callable'))
    ->willReturnCallback(function (callable $cb) { $cb(); });
```

## Mocking `$proceed`

For `around*` plugins, create `$proceed` as a simple closure mock:

```php
$proceed = fn(...$args) => $expectedResult;
// or assert it is NOT called:
$proceed = function() { $this->fail('proceed should not be called'); };
```

## Constructor setup

Mock every constructor dependency. The plugin class itself is instantiated in `setUp()`.

## Focus areas
1. Guard clauses that determine whether the plugin acts or exits immediately
2. The deferred closure logic when `addCommitCallback` is used — the closure must actually be invoked in the test
3. Return value transformation in `after*` plugins (what changes, what stays the same)
4. `around*` branching: when is `$proceed` called vs skipped
