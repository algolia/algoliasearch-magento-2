# Helper Testing Guide

## What to test
- Config value retrieval (correct scope, correct path)
- Data formatting and transformation methods
- Boolean flags and feature toggles
- Methods that aggregate multiple config values

## What NOT to test
- That `ScopeConfigInterface::getValue` works (it's core)
- Serialization/unserialization of Magento core serializers

## Key patterns

### Config helpers (ConfigHelper pattern)
ConfigHelper wraps `ScopeConfigInterface`. Test that it reads from the right path and scope, and casts/transforms the value correctly.

```php
$this->scopeConfig->method('getValue')
    ->with(ConfigHelper::APPLICATION_ID, ScopeInterface::SCOPE_STORE, $storeId)
    ->willReturn('MY_APP_ID');

$this->assertSame('MY_APP_ID', $this->configHelper->getApplicationID($storeId));
```

### Boolean flags
```php
$this->scopeConfig->method('isSetFlag')
    ->with(ConfigHelper::ENABLE_INDEXING, ScopeInterface::SCOPE_STORE)
    ->willReturn(true);

$this->assertTrue($this->configHelper->isIndexingEnabled());
```

### Serialized values (arrays stored as JSON)
```php
$this->serializer->method('unserialize')
    ->willReturn(['field' => 'name', 'searchable' => '1']);
```

## Focus areas
1. Each public getter with its expected output type
2. Default values when config is null/empty
3. Store-scoped vs global config reads
4. Any transformation logic (e.g. CSV to array, JSON decode, type cast)
