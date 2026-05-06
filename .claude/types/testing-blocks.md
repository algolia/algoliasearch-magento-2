# Block Testing Guide

## Overview

Blocks in this module span several subtypes with different testing strategies.

---

## Subtypes

### 1. Adminhtml Button Blocks (`Block/Adminhtml/*/Edit/*Button.php`)

Implement `ButtonProviderInterface`. Only contain `getButtonData()`. Do **not** extend `Template`.

**Skip entirely** — `getButtonData()` returns a hardcoded config array. No logic, no dependencies, nothing to mock. Testing it would just assert that we hardcoded the right array.

---

### 2. Adminhtml Blocks extending `Template` (`Block/Adminhtml/`)

Extend `\Magento\Backend\Block\Template` (directly or via a thin Algolia base). May have constructor dependencies beyond the standard Magento block context.

**What to test:**
- Methods with guard clauses (e.g. `isQueueActive()` — returns different values based on config).
- Methods that aggregate or transform data from injected dependencies (e.g. `getNotices()` — collects messages from multiple sources).
- Methods returning non-trivial computed values.

**Skip:**
- `_prepareLayout()` — involves Magento layout rendering infrastructure.
- Simple getters that only call `parent::` or `$this->getData()`.

**Mocking strategy:**
- Mock constructor dependencies normally.
- Do **not** instantiate via `ObjectManager` — instantiate directly with mocked deps.
- Override the context object if needed to avoid Magento bootstrap.

---

### 3. Frontend Blocks (`Block/`)

Often extend a custom Algolia base class rather than `Template` directly. May have large methods like `getConfiguration()` that build complex data structures.

**What to test:**
- Methods with branching logic (config flags, feature toggles, conditional data inclusion).
- Methods that aggregate data from multiple dependencies into a single array/structure.
- Guard clauses that produce different return shapes.

**Skip:**
- Methods that only call `parent::` or delegate to a single helper unchanged.
- `_toHtml()`, `_beforeToHtml()` — rendering lifecycle, not unit-testable.

**Mocking strategy:**
- Mock config helpers and data providers injected in the constructor.
- Avoid mocking the block's own protected methods — test through public interface only.
- For `getConfiguration()` style methods, assert specific keys in the returned array rather than asserting the entire structure.

---

## Key patterns

### Partial mock for blocks with inherited infrastructure
For frontend/adminhtml blocks that call `getRequest()`, `getCurrentCategory()`, or similar inherited Magento methods, override only those methods while keeping the logic under test intact:

```php
$block = $this->getMockBuilder(Configuration::class)
    ->setConstructorArgs([...all mocked deps...])
    ->onlyMethods(['getRequest'])
    ->getMock();

$request = $this->createMock(\Magento\Framework\App\Request\Http::class);
$request->method('getFullActionName')->willReturn('catalogsearch_result_index');
$block->method('getRequest')->willReturn($request);
```

### Asserting specific keys in large config arrays
For methods like `getConfiguration()` that return a large nested array, assert on individual keys rather than the full structure:

```php
$config = $block->getConfiguration();
$this->assertTrue($config['ccAnalytics']['enabled']);
$this->assertArrayHasKey('applicationId', $config);
```

## Focus areas
1. Guard clauses driven by config flags — each branch produces a meaningfully different output
2. Methods that aggregate data from multiple helpers — each helper's value is reflected in the result
3. Adminhtml blocks: computed boolean flags (`isQueueActive()`) and notice aggregation
4. Frontend blocks: specific key presence/absence in the JS config array based on feature toggles
