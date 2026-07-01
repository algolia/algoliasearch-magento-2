# Service Testing Guide

## What to test
- Core business logic and transformations
- Return values and data shapes
- Thrown exceptions for invalid input
- Conditional branching based on arguments or injected config

## What NOT to test
- That dependencies were constructed correctly
- Framework plumbing (DI wiring, plugin loading)
- Private helper methods directly — test them via public API

## Key patterns

### Testing return values
Services typically transform input into output. Assert on the returned value, not on internal calls.

```php
$result = $this->service->buildRecord($product);
$this->assertArrayHasKey('objectID', $result);
$this->assertSame('SKU-001', $result['sku']);
```

### Testing exceptions
```php
$this->expectException(ProductReindexingException::class);
$this->service->canProductBeReindexed($disabledProduct, $storeId);
```

### Using @dataProvider for variants
When the same logic runs differently based on input type (e.g. simple vs configurable product):

```php
#[DataProvider('productTypeProvider')]
public function testBuildRecordHandlesProductType(string $typeId, array $expectedKeys): void
```

## Focus areas
1. The main transformation/computation the service is responsible for
2. Each distinct code path (enabled/disabled, different product types, etc.)
3. Exception paths with meaningful messages
4. Edge cases: empty collections, null values, zero prices
