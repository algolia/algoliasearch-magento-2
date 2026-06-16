# Controller Testing Guide

## Overview

Magento 2 adminhtml/frontend controllers extend `Action` and interact with requests, responses, redirects, and result factories. Only test controllers that contain real logic.

## Skip these entirely

- **Route-only controllers** whose `execute()` method only creates and returns a page result (renders a layout). These contain no testable logic.
  - Example: `Controller/Adminhtml/Queue/Index.php` — just adds a success/warning message and returns a page result.

## What to test

Controllers with non-trivial `execute()` methods:
- Data validation / guard clauses (missing required fields → redirect with error message)
- Exception handling (`try/catch` blocks → verify error messages are set and redirect is returned)
- Data persistence calls (verify `save()` is called on the right model with the right data)
- Permission/ACL checks that produce different outcomes

## Mocking strategy

- Mock `RequestInterface` and configure `getParam` / `getPostValue` / `getParams` return values.
- Mock result factories (`ResultFactory`, `PageFactory`, `JsonFactory`, `RedirectFactory`) — configure them to return mocks of the respective result objects.
- Mock `MessageManager` to assert error/success/notice messages.
- Mock session objects for data persistence across redirects.
- Do **not** call `$this->dispatch()` — instantiate the controller directly.

## Redirect assertion pattern

```php
$this->redirect->expects($this->once())
    ->method('setPath')
    ->with('*/*/index');
$this->resultRedirectFactory->method('create')
    ->willReturn($this->redirect);

$result = $this->controller->execute();
$this->assertSame($this->redirect, $result);
```

## Focus areas
1. Happy path: valid input → model persisted, redirect to success URL
2. Validation failures → correct error message set on `MessageManager`, redirect back
3. Exception handling → error message set, redirect to a safe fallback route
4. Guard clauses that short-circuit `execute()` before any persistence occurs
