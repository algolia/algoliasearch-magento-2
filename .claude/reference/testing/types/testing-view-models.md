# ViewModel Testing Guide

## Overview

ViewModels implement `ArgumentInterface` and are injected into `.phtml` templates. They are pure PHP classes — no layout/rendering, no HTTP context. This makes them straightforward to unit test.

## Skip these

- Methods that are pure delegation: they call a single helper/service method and return its result with no transformation, no guard clause, and no state change. Testing these just verifies that we get what we mock.
  - Example: `getConfiguration()` that returns `$this->configHelper->getConfiguration()` verbatim.

## What to test

- Methods with **transformation logic** — filtering, mapping, combining values from multiple sources.
- Methods with **guard clauses** — early returns based on config flags, null checks, empty checks.
- Methods with **iteration** — loop over collections, apply filters, accumulate results.
- Methods that **combine data** from multiple injected dependencies.

## Example: real logic worth testing

```php
// ViewModel/Recommend/Cart.php — getAllCartItems()
// Iterates cart quote items, filters by visibility, returns array of entity IDs.
// Test: returns empty array when cart is empty
// Test: excludes items with disallowed visibility
// Test: includes items with allowed visibility
```

## Mocking strategy

- Mock all constructor dependencies.
- For methods iterating collections, use mock iterables or arrays directly.
- Assert on the **returned value** — the shape, the count, the filtered/transformed content.

## Focus areas
1. Methods with iteration + filtering — assert on the shape and content of what is returned
2. Guard clauses that short-circuit based on a config flag or null check
3. Data combination from multiple injected sources — each source can independently affect the output
