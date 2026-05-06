# Testing Guidelines specific to ProductWithoutChildren PriceManager

## Source: `Helper/Entity/Product/PriceManager/ProductWithoutChildren.php`

`ProductWithoutChildren` is the **abstract base class** of the PriceManager hierarchy. It handles price calculation for simple products (no children). Contains the constructor, shared state, and the main `addPriceData()` entry point.

---

## Concrete test double

The class is abstract. Create a minimal anonymous concrete subclass:

```php
$this->priceManager = new class(...$deps) extends ProductWithoutChildren {};
```

---

## Internal state

Methods like `addSpecialPrices()`, `addTierPrices()`, and `addCustomerGroupsPrices()` are protected and mutate `$this->customData`. Use `setPrivateProperty()` before calling via `invokeMethod()`, and `getPrivateProperty()` to assert the result.

---

## What to test

### `getFields()` (protected, use `invokeMethod()`)
Three branches based on `taxHelper->getPriceDisplayType()`:
- `DISPLAY_TYPE_EXCLUDING_TAX` → returns `['price' => false]`
- `DISPLAY_TYPE_INCLUDING_TAX` → returns `['price' => true]`
- Any other value → returns `['price' => false, 'price_with_tax' => true]`

Use `@dataProvider` for the three cases.

### `addSpecialPrices($specialPrice, $field, $currencyCode)` (protected, use `invokeMethod()`)
Two top-level paths based on `areCustomersGroupsEnabled`:

**When groups disabled:**
- Special price is set AND lower than default → updates `default`, `default_formated`, sets `default_original_formated`
- Special price is not set or higher → no change to `customData`

**When groups enabled:**
- Special price for group is lower than group price → updates group price
- Special price for group is higher → no change

### `addTierPrices($tierPrice, $field, $currencyCode)` (protected, use `invokeMethod()`)
Two paths based on `areCustomersGroupsEnabled`:
- When groups enabled and tier price is set → adds `group_{id}_tier` and `group_{id}_tier_formated`
- When groups disabled and tier price[0] is set → adds `default_tier` and `default_tier_formated`
- When tier price is falsy → no change

### `getTaxPrice($product, $amount, $withTax)` (public)
Pure delegation to `catalogHelper->getTaxPrice()`. Skip — trivially returns what we mock.

---

## Skip these

- `addPriceData()` — complex orchestrator with many interactions (currencies, groups, tax), better tested via integration
- `addCustomerGroupsPrices()` — deep Magento pricing integration
- `getSpecialPrice()` — iterates groups and calls rule pricing; integration territory
- `getTierPrice()` — complex tier price resolution; integration territory
- `formatPrice()`, `convertPrice()` — simple delegation

---

## Notes

- Set `$this->groups` to a mock iterable (array of mock Group objects) when testing group-related branches.
- Set `$this->areCustomersGroupsEnabled`, `$this->customData`, `$this->baseCurrencyCode` via `setPrivateProperty()`.
- The `priceCurrency->round()` calls on `customData` values — mock `priceCurrency->round()` to return the value passed to it for simplicity.
