# Testing Guidelines specific to ProductWithChildren PriceManager

## Source: `Helper/Entity/Product/PriceManager/ProductWithChildren.php`

`ProductWithChildren` is an **abstract** class extending `ProductWithoutChildren` (also abstract). It adds logic for calculating min/max price ranges across child products (used for configurables, bundles, grouped products).

---

## Concrete test double

Both `ProductWithChildren` and `ProductWithoutChildren` are abstract. Create a minimal anonymous concrete subclass in the test file:

```php
$this->priceManager = new class(...$deps) extends ProductWithChildren {};
```

---

## Internal state

Many methods mutate `$this->customData` directly and return void. Use `setPrivateProperty()` to initialize state before calling these methods, and `getPrivateProperty()` to assert the result:

```php
$this->setPrivateProperty($this->priceManager, 'customData', ['price' => ['USD' => ['default' => 10.0]]]);
$this->setPrivateProperty($this->priceManager, 'groups', $mockGroupCollection);
$this->setPrivateProperty($this->priceManager, 'areCustomersGroupsEnabled', false);
$this->setPrivateProperty($this->priceManager, 'baseCurrencyCode', 'USD');
```

---

## What to test

### `formattedConfigPrice($min, $max, $currencyCode)` (public)
Pure logic:
- When `$min == $max` → returns single formatted price (delegates to `formatPrice`)
- When `$min != $max` → returns dashed format (delegates to `getDashedPriceFormat`)

### `handleOriginalPrice($field, $currencyCode, $min, $max, $minOriginal, $maxOriginal)` (public)
Complex branching:
- When `$min !== $max` and prices differ from originals, and originals differ from each other → sets dashed original format
- When `$min !== $max` and prices differ from originals, and originals are equal → sets single original format
- When `$min === $max` and `$min < $minOriginal` → sets original price format
- When `$min === $max` and `$min >= $minOriginal` → does not set original format

### `handleGroupOrginalPriceformated($field, $currencyCode, $formatedPrice)` (public)
Conditional on `areCustomersGroupsEnabled`:
- When enabled → sets `group_{id}_original_formated` for each group
- When disabled → does nothing to `customData`

### `getDashedPriceFormat($min, $max, $currencyCode)` (protected, use `invokeMethod()`)
- When `$min === $max` → returns `''`
- When `$min !== $max` → returns `'formated_min - formated_max'`

---

## Skip these

- `addAdditionalData()` — orchestrator method; its logic is tested through the public methods above
- `getMinMaxPrices()`, `setFinalGroupPrices()`, `getGroupPriceList()`, `formatMinArray()` — deeply entangled with Magento product/pricing infrastructure; test via integration
- `handleNonEqualMinMaxPrices()` — state mutation with many conditions; integration test territory

---

## Notes

- `$this->groups` is a Magento collection — mock it as an iterable (e.g., an array of mock Group objects) when testing methods that iterate over it.
- `formatPrice()` delegates to `priceCurrency` — mock it to return a predictable string.
