# Upgrading Magento 2 Unit Tests from PHPUnit 10 to PHPUnit 12

Magento 2.4.9 ships with PHPUnit 12 and PHP 8.5, up from PHPUnit 10 and PHP 8.3 in 2.4.8. PHPUnit 12 is the most breaking upgrade in several years: it removes APIs that have been deprecated since PHPUnit 9, drops docblock annotation support entirely, and introduces a new notice system that fires when mocks are created without expectations.

This article documents every breaking change we hit when migrating the Algolia Search extension test suite (421 tests), how we fixed each one, and the patterns we settled on going forward.

---

## The stack

| | Before (Magento 2.4.8) | After (Magento 2.4.9) |
|---|---|---|
| PHPUnit | 10 | 12 |
| PHP | 8.3 | 8.5 |

---

## Breaking change 1 — `@dataProvider` docblock removed

PHPUnit 12 dropped all docblock annotation support. The `@dataProvider` tag no longer works and must be replaced with the `#[DataProvider]` PHP attribute.

**Before:**
```php
/**
 * @dataProvider priceKeyDataProvider
 */
public function testGetPriceKeyWithVariousConfigurations(...): void
```

**After:**
```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('priceKeyDataProvider')]
public function testGetPriceKeyWithVariousConfigurations(...): void
```

The `use` import is required — the attribute is not in the global namespace.

**Watch out for multi-line docblocks.** If a docblock contains a description *before* `@dataProvider`, a simple single-line regex won't match it. You need to strip the entire docblock and replace it:

```php
/**
 * Tests the queue processes valid handlers.
 *
 * @dataProvider authorizedHandlersProvider
 */
// → becomes just:
#[DataProvider('authorizedHandlersProvider')]
```

---

## Breaking change 2 — `addMethods()` removed

`addMethods()` was introduced as a workaround to mock methods that don't exist on a class — mainly to handle PHP magic methods or `@method` docblock annotations. PHPUnit 12 removed it.

In a Magento context, this pattern appeared frequently with classes that extend `DataObject`, whose `getData`, `setData`, `getProductId`, `getChangedProductIds`, etc. are all dispatched through `__call`.

**Before:**
```php
$stockItem = $this->getMockBuilder(AbstractModel::class)
    ->disableOriginalConstructor()
    ->addMethods(['getProductId'])
    ->getMock();
$stockItem->method('getProductId')->willReturn(42);
```

**After:**
```php
$stockItem = $this->getMockBuilder(AbstractModel::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['__call'])
    ->getMock();
$stockItem->method('__call')
    ->willReturnCallback(fn($name, $args) => $name === 'getProductId' ? 42 : null);
```

`__call` is a real declared method, so `onlyMethods(['__call'])` is valid. The callback receives the method name and its arguments, letting you dispatch to different return values per method name in a single stub.

If a mock needs to respond to multiple magic methods:

```php
$category->method('__call')
    ->willReturnCallback(function (string $name, array $args) {
        return match ($name) {
            'getChangedProductIds' => [1, 2, 3],
            'getStoreId'           => 5,
            default                => null,
        };
    });
```

**Important:** If you also need to stub real declared methods on the same object, list them in `onlyMethods()` alongside `__call`:

```php
->onlyMethods(['getId', 'getOrigData', 'getData', '__call'])
```

---

## Breaking change 3 — `getMockForAbstractClass()` removed

Both the chained `->getMockForAbstractClass()` builder method and the standalone `$this->getMockForAbstractClass()` test case method were removed in PHPUnit 12.

The replacement is simply `createMock()`. In PHPUnit 12, `createMock()` handles abstract classes natively — it automatically generates stub implementations for any abstract methods.

**Before:**
```php
$this->subject = $this->getMockBuilder(AbstractBlock::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['getNameInLayout'])
    ->getMockForAbstractClass();
```

**After:**
```php
$this->subject = $this->createMock(AbstractBlock::class);
```

The builder chain collapses entirely. If you need to stub specific methods, configure them on the resulting mock after creation as usual.

---

## Breaking change 4 — `isType('callable')` removed

PHPUnit 12 removed `isType('callable')` as a constraint (it was deprecated in PHPUnit 11). The replacement is the dedicated `isCallable()` method.

```php
// PHPUnit 11 deprecated, PHPUnit 12 removed:
->with($this->isType('callable'))

// PHPUnit 12:
->with($this->isCallable())
```

**However**, if your test suite must also run on PHPUnit 10 (e.g. because you support both Magento 2.4.8 and 2.4.9), `isCallable()` does not exist in PHPUnit 10 and will throw a fatal error. The cross-version solution is `callback('is_callable')`, which has been available since PHPUnit 4:

```php
// Works on PHPUnit 10, 11, and 12:
->with($this->callback('is_callable'))
```

`callback()` wraps any PHP callable as a constraint. Passing `'is_callable'` delegates to PHP's native type check.

---

## Breaking change 5 — Interface mocks can't add undeclared methods

This is related to the removal of `addMethods()` but worth calling out separately because it affects a Magento-specific pattern.

When you mock an interface, you can only stub methods the interface actually declares. If you need a method that only exists on the concrete implementation, mock the concrete class instead.

**Before:**
```php
// StoreInterface does not declare getCurrentCurrencyCode()
$storeMock = $this->getMockBuilder(StoreInterface::class)
    ->addMethods(['getCurrentCurrencyCode'])
    ->getMockForAbstractClass();
```

**After:**
```php
// Store::class declares getCurrentCurrencyCode()
$storeMock = $this->createMock(Store::class);
```

The concrete class satisfies the type hint everywhere `StoreInterface` is expected, and you get the real method to stub without any workarounds.

---

## New in PHPUnit 12 — Notices for mocks without expectations

After fixing all errors, we were left with 385 PHPUnit Notices — a new category introduced in PHPUnit 12. A notice fires whenever `createMock()` is used but no `expects()` call is made on that mock in the test. PHPUnit's reasoning: if you're not asserting calls, you want a stub, not a mock.

```
PHPUnit\Framework\MockObject\Rule\AnyInvokedCount: Mock object created
for class Foo, but no expectations were set on it. Use createStub() instead.
```

### Option A — Convert to `createStub()` (semantically correct)

`createStub()` is the right tool when you only need a dependency to return a value. It does not record calls or enforce expectations.

```php
// Before — wrong tool, triggers notice
$this->configHelper = $this->createMock(ConfigHelper::class);

// After — correct tool, no notice
$this->configHelper = $this->createStub(ConfigHelper::class);
```

**Catch:** `createMock()` returns `MockObject&T`; `createStub()` returns `Stub&T`. `MockObject extends Stub`, not the reverse. If your test class declares properties with intersection types:

```php
protected null|(ConfigHelper&MockObject) $configHelper = null;
```

You must update them to `(ConfigHelper&Stub)` or `ConfigHelper` when switching to `createStub()`. Otherwise PHP throws a type error at assignment.

### Option B — `#[AllowMockObjectsWithoutExpectations]` (pragmatic)

PHPUnit provides an escape hatch for test classes where mocks and stubs are mixed — or where you simply don't want to audit every mock in the file:

```php
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class FooTest extends TestCase
{
    // createMock() calls no longer trigger notices in this class
}
```

Applied at class level, it suppresses all notices for that test class. This is what we chose for 54 files where auditing each mock individually would have been a large amount of churn with no behavioral benefit.

---

## Magento-specific testing patterns

Beyond the PHPUnit 12 breaking changes, the migration was also an opportunity to standardise a few recurring patterns in Magento block tests.

### Partial mocks for blocks with many constructor dependencies

Magento blocks often have 20+ constructor parameters. Supplying them all in `setConstructorArgs()` is fragile — any signature change breaks unrelated tests.

**Before:**
```php
$block = $this->getMockBuilder(Configuration::class)
    ->setConstructorArgs([
        $this->context,
        $this->configHelper,
        $this->instantSearchConfig,
        // ... 21 more arguments
    ])
    ->onlyMethods(['getRequest', 'getCurrentCategory'])
    ->getMock();
```

**After:**
```php
$block = $this->createPartialMock(
    Configuration::class,
    ['getRequest', 'getCurrentCategory']
);
// Inject only what the method under test actually reads:
$this->setPrivateProperty($block, 'instantSearchConfig', $this->instantSearchConfig);
```

This works when all dependencies are promoted constructor properties (the pattern Algolia's blocks follow). Skipping the constructor is safe because `setPrivateProperty` (a reflection helper on the base `TestCase`) sets each property directly. You inject only the properties the test actually exercises, rather than wiring up the entire dependency tree.

**When not to use this pattern:** If the constructor performs non-trivial logic beyond assignment (calling `parent::__construct` with side effects, registering listeners, etc.), you must run the constructor and supply all its arguments.

### Guard-clause helpers in complex tests

When a class has guards at the top of its main method (credentials check, feature flag check, etc.), `setUp()` is the wrong place to configure their return values. The first `willReturn` registered wins, so any test that needs to verify the guard itself can't override a setUp default.

**Problematic pattern:**
```php
protected function setUp(): void
{
    // This prevents any test from testing the guard-fails case
    $this->credentials->method('checkCredentials')->willReturn(true);
}
```

**Better pattern — opt-in helper:**
```php
protected function setUp(): void
{
    // No defaults set here
}

private function allowPassingGuards(): void
{
    $this->credentials->method('checkCredentials')->willReturn(true);
    $this->helper->method('isIndexingEnabled')->willReturn(true);
}

public function testDoesNothingWhenCredentialsInvalid(): void
{
    // Does NOT call allowPassingGuards() — guard fires, method exits early
    $this->credentials->method('checkCredentials')->willReturn(false);
    $this->configurator->saveConfigurationToAlgolia();
    $this->indexer->expects($this->never())->method('setSettings');
}

public function testCallsSetSettingsWhenGuardsPassed(): void
{
    $this->allowPassingGuards(); // opt in to happy path
    $this->configurator->saveConfigurationToAlgolia();
    $this->indexer->expects($this->once())->method('setSettings');
}
```

---

## Summary

| Issue | PHPUnit 10 | PHPUnit 12 |
|---|---|---|
| Data providers | `@dataProvider` docblock | `#[DataProvider('name')]` attribute |
| Non-declared method mocks | `addMethods(['foo'])` | `onlyMethods(['__call'])` + `willReturnCallback` |
| Abstract class mocks | `->getMockForAbstractClass()` | `createMock(AbstractClass::class)` |
| Callable constraint | `isType('callable')` | `isCallable()` (or `callback('is_callable')` for cross-version) |
| Stub-only dependencies | `createMock()` (no notice) | `createStub()` or `#[AllowMockObjectsWithoutExpectations]` |
